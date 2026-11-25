<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers\Admin;

use App\Core\Menus\MenuService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Roles\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Users > (user) > Permissions: extra permissions for ONE person, on top of their roles.
 *
 * Example: every Editor can't create posts, but Priya (also an Editor) can.
 * Tick blog.posts.create here for Priya instead of changing the Editor role.
 *
 *   effective permissions = permissions of all their roles + these extras
 *
 * Spatie stores the extras in model_has_permissions; $user->can() and the sidebar include them automatically.
 *
 * Safety rules:
 *  - needs users.permissions
 *  - you can only grant permissions you have yourself (super-admin can grant all)
 *  - you can't change your own extras
 *  - super-admin users don't need extras (they pass every check)
 */
class UserPermissionController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PermissionCatalog $catalog) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:users.permissions')];
    }

    public function edit(Request $request, User $user, MenuService $menus): View
    {
        $user->load('roles.permissions', 'permissions');
        $fromRoles = $user->getPermissionsViaRoles()->pluck('name')->unique()->values()->all();

        return view('users::admin.permissions', [
            'user' => $user,
            'fromRoles' => $fromRoles,
            'extras' => $user->getDirectPermissions()->pluck('name')->all(),
            'grantable' => $this->grantable($request->user()),
            'resources' => $this->catalog->resources(),
            'actions' => $this->catalog->standardActions(),
            'special' => $this->catalog->special(),
            'locked' => $user->isSuperAdmin() || $user->is($request->user()),
            'sidebar' => $menus->adminSidebar($user),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 422, 'Super admins pass every permission check already.');
        abort_if($user->is($request->user()), 403, 'You cannot change your own permissions.');

        $data = $request->validate(['permissions' => ['array'], 'permissions.*' => ['string', 'max:100']]);

        $grantable = $this->grantable($request->user());
        $known = $this->catalog->all()->all();
        $current = $user->getDirectPermissions()->pluck('name')->all();

        // Ticked boxes you are allowed to grant + existing extras you can't touch (kept as they were).
        $wanted = array_intersect($data['permissions'] ?? [], $grantable, $known);
        $kept = array_diff($current, $grantable);
        $final = array_values(array_unique([...$wanted, ...$kept]));

        $user->syncPermissions(array_map(fn (string $n) => Permission::findOrCreate($n, 'web'), $final));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $added = array_values(array_diff($final, $current));
        $removed = array_values(array_diff($current, $final));
        if ($added || $removed) {
            activity('users')->performedOn($user)->causedBy($request->user())->event('permissions')
                ->withProperties(['added' => $added, 'removed' => $removed])
                ->log('Changed extra permissions'.($added ? ' +'.implode(', +', $added) : '').($removed ? ' -'.implode(', -', $removed) : ''));
        }

        return redirect()->route('admin.users.permissions', $user)
            ->with('success', count($final) ? "{$user->name} now has ".count($final).' extra permission(s) on top of their roles.' : "{$user->name} has only the permissions of their roles.");
    }

    /**
     * Permissions the signed-in admin may hand out: all for super-admin, otherwise only their own.
     *
     * @return array<int, string>
     */
    private function grantable(User $actor): array
    {
        return $actor->isSuperAdmin()
            ? $this->catalog->all()->all()
            : $actor->getAllPermissions()->pluck('name')->all();
    }
}
