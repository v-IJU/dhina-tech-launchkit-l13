<?php

declare(strict_types=1);

namespace App\Modules\Roles\Controllers\Admin;

use App\Core\Menus\MenuService;
use App\Http\Controllers\Controller;
use App\Models\Role;
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

class PermissionController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PermissionCatalog $catalog) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:permissions.view', only: ['index']),
            new Middleware('permission:permissions.create', only: ['store']),
            new Middleware('permission:permissions.delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $roles = Role::query()->with('permissions')->orderBy('id')->get();
        $special = $this->catalog->special();

        $rows = $this->catalog->resources()->map(function (array $resource) use ($roles, $special) {
            $names = collect($resource['actions'])->map(fn ($a) => $resource['key'].'.'.$a)
                ->merge($special->filter(fn ($s) => str_starts_with($s, $resource['key'].'.')))->unique()->values();

            return [
                'label' => $resource['label'],
                'key' => $resource['key'],
                'permissions' => $names->map(fn ($n) => [
                    'name' => $n,
                    'custom' => ! $this->catalog->declaredAnywhere($n),
                    'id' => Permission::query()->where('name', $n)->value('id'),
                ]),
                'roles' => $roles->filter(fn ($r) => $r->name === config('starter.super_admin_role')
                    || $r->permissions->pluck('name')->intersect($names)->isNotEmpty())
                    ->map(fn ($r) => $r->display_name ?: $r->name)->join(', '),
            ];
        });

        return view('roles::admin.permissions', [
            'rows' => $rows,
            'total' => $this->catalog->all()->count(),
            'resources' => $this->catalog->resources()->pluck('label', 'key'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'resource' => ['required', Rule::in($this->catalog->resources()->pluck('key'))],
            'action' => ['required', 'string', 'max:40'],
        ]);

        $name = $data['resource'].'.'.Str::slug($data['action']);

        if (Permission::query()->where('name', $name)->exists()) {
            return back()->withErrors(['action' => 'This permission already exists.'])->withInput();
        }

        Permission::create(['name' => $name, 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Permission {$name} created. Tick it on a role to grant it. Check it in code with @can('{$name}').");
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($this->catalog->declaredAnywhere($permission->name)) {
            return back()->with('error', 'This permission comes from a module.json file. Remove it there instead.');
        }

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(MenuService::class)->flush();

        return back()->with('success', "Permission {$permission->name} removed from every role.");
    }
}
