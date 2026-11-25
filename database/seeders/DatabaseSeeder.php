<?php

namespace Database\Seeders;

use App\Core\Menus\MenuItem;
use App\Core\Menus\MenuService;
use App\Core\Permissions\PermissionSync;
use App\Core\Settings\SettingsRepository;
use App\Models\Role;
use App\Modules\Settings\Support\SettingDefinitions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base data every install needs. Safe to run again: nothing is duplicated
 * and nothing you changed in the admin panel is overwritten.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionSync::class)->run();
        app(MenuService::class)->syncFromModules();

        $this->seedRoles();
        $this->seedSettings();
        $this->seedWebsiteMenus();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(MenuService::class)->flush();
    }

    private function seedRoles(): void
    {
        $roles = [
            'super-admin' => ['Super Admin', 'Full access to everything. Bypasses all permission checks.', '#2563eb', null],
            'admin' => ['Admin', 'Manages users, content and settings. Cannot install modules or delete roles.', '#ea580c', fn (string $p) => ! Str::is([
                'modules.*', 'roles.delete', 'permissions.create', 'permissions.delete', 'backups.delete', 'activity.delete',
            ], $p)],
            'editor' => ['Editor', 'Writes and publishes blog posts and keeps website content up to date.', '#16a34a', fn (string $p) => Str::is([
                'dashboard.view', 'blog.*', 'categories.*', 'media.view', 'media.create', 'media.edit',
                'pages.view', 'pages.edit', 'sliders.*', 'services.*', 'testimonials.*', 'faqs.*', 'inbox.view',
            ], $p) && ! Str::is(['*.delete'], $p)],
        ];

        $all = Permission::query()->pluck('name');

        foreach ($roles as $name => [$label, $description, $color, $filter]) {
            $role = Role::findOrCreate($name, 'web');
            $isNew = $role->wasRecentlyCreated || ! $role->display_name;
            $role->update(['display_name' => $role->display_name ?: $label, 'description' => $role->description ?: $description, 'color' => $isNew ? $color : $role->color]);

            // Only fill permissions for roles that have none yet, so edits made in the UI stay.
            if ($filter && $role->permissions()->count() === 0) {
                $role->syncPermissions($all->filter($filter)->values());
            }
        }
    }

    private function seedSettings(): void
    {
        $existing = DB::table('settings')->pluck('key')->all();
        $missing = array_diff_key(SettingDefinitions::defaults(), array_flip($existing));

        if ($missing) {
            app(SettingsRepository::class)->set($missing);
        }
    }

    private function seedWebsiteMenus(): void
    {
        if (MenuItem::query()->whereIn('location', ['header', 'footer'])->exists()) {
            return;
        }

        $menus = [
            'header' => ['Home' => '/', 'About' => '/about', 'Services' => '/services', 'Blog' => '/blog', 'Contact' => '/contact'],
            'footer' => ['About us' => '/about', 'Services' => '/services', 'Blog' => '/blog', 'Contact' => '/contact', 'Privacy policy' => '/privacy-policy'],
        ];

        foreach ($menus as $location => $items) {
            $order = 1;
            foreach ($items as $label => $url) {
                MenuItem::query()->create(['location' => $location, 'label' => $label, 'url' => $url, 'sort_order' => $order++]);
            }
        }
    }
}
