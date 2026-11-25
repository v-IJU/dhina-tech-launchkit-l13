<?php

declare(strict_types=1);

namespace App\Core\Menus;

use App\Core\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Builds the admin sidebar and the website menus from the menu_items table.
 *
 * The sidebar is filtered per user: an item shows only when its module is
 * enabled, its route exists and the user has its permission. Results are
 * cached per permission-set and cleared with flush() whenever menus, roles
 * or modules change.
 */
class MenuService
{
    private const VERSION_KEY = 'menus.version';

    public function __construct(private readonly ModuleManager $modules) {}

    /**
     * @return array<int, array{title: string, items: array<int, array<string, mixed>>}>
     */
    public function adminSidebar(User $user): array
    {
        $isSuper = $user->hasRole(config('starter.super_admin_role'));
        $permissions = $isSuper ? ['*'] : $user->getAllPermissions()->pluck('name')->sort()->values()->all();
        // Modules available here are part of the key: a tenant's plan change shows up straight away.
        $modules = $this->modules->all()->filter(fn ($m) => $this->modules->isEnabled($m->slug))->keys()->implode(',');
        $key = 'menus.admin.'.$this->version().'.'.md5(implode('|', $permissions).'#'.$modules);

        return Cache::remember($key, now()->addDay(), function () use ($user) {
            $items = MenuItem::query()
                ->where('location', 'admin')->whereNull('parent_id')->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')->get();

            $groups = [];
            foreach ($items as $item) {
                $entry = $this->sidebarEntry($item, $user);
                if ($entry) {
                    $groups[$item->group ?: 'Modules'][] = $entry;
                }
            }

            $order = array_flip(config('starter.menu_groups'));
            uksort($groups, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

            return collect($groups)->map(fn ($items, $title) => ['title' => $title, 'items' => $items])->values()->all();
        });
    }

    /**
     * Every admin sidebar item with the permission it needs, before permission filtering.
     * Used by the Roles page to preview a role's sidebar live while boxes are ticked
     * (resources/admin/js/role-matrix.js applies the same rules as sidebarEntry()).
     *
     * @return array<int, array{title: string, items: array<int, array{label: string, icon: string, permission: string|null, ownLink: bool, children: array<int, array{label: string, permission: string|null}>}>}>
     */
    public function adminTree(): array
    {
        $groups = [];
        $items = MenuItem::query()
            ->where('location', 'admin')->whereNull('parent_id')->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->get();

        foreach ($items as $item) {
            if ($item->module && ! $this->modules->isEnabled($item->module)) {
                continue;
            }

            $children = $item->children
                ->filter(fn (MenuItem $c) => (! $c->module || $this->modules->isEnabled($c->module)) && $c->route && Route::has($c->route))
                ->map(fn (MenuItem $c) => ['label' => $c->label, 'permission' => $c->permission])->values()->all();
            $ownRoute = $item->route && Route::has($item->route);

            // Same rule as sidebarEntry(): a parent with its own page + children shows its page as the first child.
            if ($children && $ownRoute) {
                array_unshift($children, ['label' => $item->label, 'permission' => $item->permission]);
            }

            $groups[$item->group ?: 'Modules'][] = [
                'label' => $item->label,
                'icon' => $item->icon ?: 'bi-grid',
                'permission' => $item->permission,
                'ownLink' => $ownRoute || (bool) $item->url,
                'children' => $children,
            ];
        }

        $order = array_flip(config('starter.menu_groups'));
        uksort($groups, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        return collect($groups)->map(fn ($items, $title) => ['title' => $title, 'items' => $items])->values()->all();
    }

    /**
     * Website menu (header or footer).
     *
     * @return array<int, array{label: string, url: string, new_tab: bool, children: array<int, mixed>}>
     */
    public function website(string $location): array
    {
        $locale = app()->getLocale();

        return Cache::remember('menus.'.$location.'.'.$locale.'.'.$this->version(), now()->addDay(), function () use ($location, $locale) {
            return MenuItem::query()
                ->where('location', $location)->whereNull('parent_id')->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')->get()
                ->map(fn (MenuItem $i) => [
                    'label' => $i->labelFor($locale),
                    'url' => $i->href(),
                    'new_tab' => $i->new_tab,
                    'children' => $i->children->map(fn (MenuItem $c) => ['label' => $c->labelFor($locale), 'url' => $c->href(), 'new_tab' => $c->new_tab])->all(),
                ])->all();
        });
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    /**
     * Create sidebar entries declared in each module.json. Existing entries keep
     * their label, icon, order and visibility so changes made in the Menu
     * builder are never overwritten.
     */
    public function syncFromModules(): int
    {
        $created = 0;
        $keys = [];

        foreach ($this->modules->all() as $module) {
            foreach ($module->menu as $i => $def) {
                $parent = $this->upsert($def, $module->slug, null, ($def['order'] ?? ($module->order + $i)), $created);
                $keys[] = $parent->key;

                foreach ($def['children'] ?? [] as $j => $child) {
                    $keys[] = $this->upsert($child + ['group' => $def['group'] ?? null], $module->slug, $parent->id, $j + 1, $created)->key;
                }
            }
        }

        // Remove sidebar entries of modules whose folder was deleted.
        MenuItem::query()->where('location', 'admin')->whereNotNull('key')->whereNotIn('key', $keys)->delete();

        $this->flush();

        return $created;
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function upsert(array $def, string $module, ?int $parentId, int $order, int &$created): MenuItem
    {
        $key = $def['key'] ?? $module.'.'.Str::slug($def['label']);
        $item = MenuItem::query()->firstOrNew(['location' => 'admin', 'key' => $key]);

        if (! $item->exists) {
            $item->fill([
                'label' => $def['label'],
                'icon' => $def['icon'] ?? null,
                'group' => $def['group'] ?? 'Modules',
                'sort_order' => $order,
                'is_active' => true,
            ]);
            $created++;
        }

        $item->fill([
            'module' => $module,
            'parent_id' => $parentId,
            'route' => $def['route'] ?? null,
            'permission' => $def['permission'] ?? null,
        ])->save();

        return $item;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sidebarEntry(MenuItem $item, User $user): ?array
    {
        if (! $this->allowed($item, $user)) {
            return null;
        }

        $children = $item->children
            ->filter(fn (MenuItem $c) => $this->allowed($c, $user) && $c->route && Route::has($c->route))
            ->map(fn (MenuItem $c) => [
                'label' => $c->label,
                'route' => $c->route,
                'active' => $this->activePattern($c->route),
            ])->values()->all();

        $ownLink = ($item->route && Route::has($item->route)) || $item->url;

        // Parent with its own page + visible children: keep its page as the first child link.
        if ($children && $item->route && Route::has($item->route)) {
            array_unshift($children, [
                'label' => $item->label,
                'route' => $item->route,
                'active' => $this->activePattern($item->route),
            ]);
        }

        // Nothing clickable at all → hide. A parent whose children are all hidden
        // (missing route / no permission) falls back to a plain link to its own page.
        if (empty($children) && ! $ownLink) {
            return null;
        }

        return [
            'key' => $item->key ?? 'item-'.$item->id,
            'label' => $item->label,
            'icon' => $item->icon ?: 'bi-grid',
            'route' => $item->route,
            'url' => $item->url,
            'active' => $item->route ? $this->activePattern($item->route) : null,
            'children' => $children,
        ];
    }

    private function allowed(MenuItem $item, User $user): bool
    {
        if ($item->module && ! $this->modules->isEnabled($item->module)) {
            return false;
        }

        return ! $item->permission || $user->can($item->permission);
    }

    /**
     * "admin.blog.posts.index" -> "admin.blog.posts.*" so create/edit pages
     * keep the menu item highlighted.
     */
    private function activePattern(string $route): string
    {
        return Str::endsWith($route, '.index') ? Str::beforeLast($route, '.index').'.*' : $route;
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
