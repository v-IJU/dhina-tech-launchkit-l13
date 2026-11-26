<?php

declare(strict_types=1);

namespace App\Modules\Roles\Controllers\Admin;

use App\Core\Menus\MenuService;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Modules\Roles\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller implements HasMiddleware
{
    public const COLORS = ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0891b2', '#475569'];

    public function __construct(private readonly PermissionCatalog $catalog) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.view', only: ['index']),
            new Middleware('permission:roles.create', only: ['create', 'store']),
            new Middleware('permission:roles.edit', only: ['edit', 'update']),
            new Middleware('permission:roles.delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $roles = Role::query()->withCount(['users', 'permissions'])->with(['users' => fn ($q) => $q->latest()->limit(5)])->orderBy('id')->get();

        return view('roles::admin.index', [
            'roles' => $roles,
            'total' => $this->catalog->all()->count(),
            'grantable' => auth()->user()?->isSuperAdmin() ? null : auth()->user()?->grantablePermissions(),
        ]);
    }

    public function create(Request $request): View
    {
        $role = new Role(['color' => '#0891b2']);
        $checked = ['dashboard.view'];

        // "Duplicate" on the roles page opens this form pre-filled from another role.
        if ($from = Role::query()->where('name', $request->query('from'))->first()) {
            $role->fill(['display_name' => ($from->display_name ?: $from->name).' copy', 'description' => $from->description, 'color' => $from->color]);
            $checked = $this->rolePermissions($from);
        }

        return view('roles::admin.form', $this->formData($role, $checked));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $role = new Role([
            'name' => $data['name'],
            'guard_name' => 'web',
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'],
        ]);
        $role->save();
        $role->syncPermissions($this->ensurePermissions($this->allowedChange($request, $role, $data['permissions'] ?? [])));
        $this->flush();

        activity('roles')->performedOn($role)->event('created')->log("Created role {$role->display_name} with ".count($data['permissions'] ?? []).' permissions');

        return redirect()->route('admin.roles.index')->with('success', "Role “{$role->display_name}” created.");
    }

    public function edit(Request $request, Role $role): View
    {
        $this->guardRole($request, $role);

        return view('roles::admin.form', $this->formData($role, $this->rolePermissions($role)));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->guardRole($request, $role);
        $data = $this->validated($request, $role);

        $role->update([
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'],
        ]);

        if ($role->name !== config('starter.super_admin_role')) {
            $role->syncPermissions($this->ensurePermissions($this->allowedChange($request, $role, $data['permissions'] ?? [])));
        }
        $this->flush();

        activity('roles')->performedOn($role)->event('updated')->log("Updated role {$role->display_name}");

        return redirect()->route('admin.roles.index')->with('success', "Role “{$role->display_name}” saved. Changes apply immediately.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->guardRole($request, $role);
        if (in_array($role->name, config('starter.system_roles'), true)) {
            return back()->with('error', 'System roles cannot be deleted.');
        }
        if ($count = $role->users()->count()) {
            return back()->with('error', "Reassign {$count} user(s) to another role before deleting “{$role->display_name}”.");
        }

        $role->delete();
        $this->flush();

        return back()->with('success', 'Role deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        $request->merge(['name' => $role instanceof Role ? $role->name : Str::slug((string) $request->input('display_name'))]);

        return $request->validate([
            'display_name' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::in(self::COLORS)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'max:100'],
        ], ['name.unique' => 'A role with this name already exists.']);
    }

    /**
     * @param  array<int, string>  $checked
     * @return array<string, mixed>
     */
    private function formData(Role $role, array $checked): array
    {
        return [
            'role' => $role,
            'checked' => $checked,
            'resources' => $this->catalog->resources(),
            'actions' => $this->catalog->standardActions(),
            'special' => $this->catalog->special(),
            'total' => $this->catalog->all()->count(),
            'grantable' => auth()->user()?->isSuperAdmin() ? null : auth()->user()?->grantablePermissions(),
            'colors' => self::COLORS,
            'copyFrom' => Role::query()->with('permissions')->orderBy('id')->get()->map(fn (Role $r) => [
                'name' => $r->name,
                'label' => $r->display_name ?: $r->name,
                'permissions' => $r->name === config('starter.super_admin_role') ? ['*'] : $r->permissions->pluck('name')->all(),
            ]),
            'locked' => $role->name === config('starter.super_admin_role'),
            'menuTree' => app(MenuService::class)->adminTree(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function rolePermissions(Role $role): array
    {
        return $role->name === config('starter.super_admin_role')
            ? $this->catalog->all()->all()
            : $role->loadMissing('permissions')->permissions->pluck('name')->all();
    }

    /**
     * Only permissions that exist in the catalog can be granted.
     *
     * @param  array<int, string>  $names
     * @return array<int, \Spatie\Permission\Contracts\Permission>
     */
    private function ensurePermissions(array $names): array
    {
        $allowed = $this->catalog->all();

        return collect($names)->filter(fn ($n) => $allowed->contains($n))
            ->map(fn ($n) => Permission::findOrCreate($n, 'web'))->all();
    }

    /**
     * You can't edit your own role (or you could give yourself anything), nor a role that has
     * permissions you don't have. Super admins can edit every role.
     */
    private function guardRole(Request $request, Role $role): void
    {
        /** @var User $me */
        $me = $request->user();
        if ($me->isSuperAdmin()) {
            return;
        }
        abort_if($me->hasRole($role->name), 403, 'You cannot change your own role. Ask a super admin.');
        abort_unless($me->canGiveRole($role), 403, 'This role has permissions you don\'t have, so you can\'t change it.');
    }

    /**
     * Ticked boxes you may grant (permissions you have yourself) + what the role already had that you
     * can't grant (kept as it was, never silently removed).
     *
     * @param  array<int, string>  $wanted
     * @return array<int, string>
     */
    private function allowedChange(Request $request, Role $role, array $wanted): array
    {
        /** @var User $me */
        $me = $request->user();
        if ($me->isSuperAdmin()) {
            return $wanted;
        }
        $grantable = $me->grantablePermissions();
        $current = $role->exists ? $role->permissions->pluck('name')->all() : [];

        return array_values(array_unique(array_merge(
            array_intersect($wanted, $grantable),
            array_diff($current, $grantable),
        )));
    }

    private function flush(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(MenuService::class)->flush();
    }
}
