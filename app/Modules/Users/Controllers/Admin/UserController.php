<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Import\CsvImport;
use App\Core\Import\HandlesImport;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Modules\Users\Requests\UserRequest;
use App\Notifications\AdminAlert;
use App\Notifications\WelcomeUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller implements HasMiddleware
{
    use HandlesImport;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.view', only: ['index']),
            new Middleware('permission:users.create', only: ['create', 'store']),
            new Middleware('permission:users.edit', only: ['edit', 'update', 'bulk']),
            new Middleware('permission:users.delete', only: ['destroy', 'restore', 'forceDelete']),
            new Middleware('permission:users.import', only: ['importForm', 'import', 'importTemplate']),
        ];
    }

    /**
     * Users list: search, role/status filters, joined date range, sorting,
     * per-page, CSV export and bulk actions (see app/Core/DataTable/DataTable.php).
     */
    public function index(Request $request): Response
    {
        return DataTable::of(User::query()->with(['roles', 'permissions']), $request)
            ->search(fn ($query, string $term) => $query->search($term))
            ->sortable(['name', 'username', 'status', 'last_login_at', 'created_at'], default: 'created_at')
            ->filter('role', 'Role', $this->roleOptions(), fn ($query, string $role) => $query->role($role))
            ->status('status', ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'])
            ->dateRange('created_at', 'Joined')
            ->export('users', [
                'ID' => 'id',
                'Name' => 'name',
                'Email' => 'email',
                'Username' => 'username',
                'Phone' => 'phone',
                'Role' => fn (User $u) => $u->getRoleNames()->join(', '),
                'Status' => 'status',
                'Department' => 'department',
                'Job title' => 'job_title',
                'Last login' => 'last_login_at',
                'Joined' => 'created_at',
            ], 'users.export')
            ->trash('users.delete')
            ->bulk(route('admin.users.bulk'), ['activate' => 'users.edit', 'deactivate' => 'users.edit', 'delete' => 'users.delete'])
            ->render('users::admin.index', 'users::admin._table', ['roles' => $this->roleOptions()]);
    }

    public function create(): View
    {
        return view('users::admin.form', ['user' => new User(['status' => 'active', 'country' => 'India']), 'roles' => $this->roleOptions()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $this->userData($request);
        $data['password'] = Hash::make($request->validated('password'));

        $user = User::query()->create($data);
        $user->forceFill(['email_verified_at' => $request->boolean('email_verified') ? now() : null])->save();
        $user->syncRoles([$request->validated('role')]);

        if ($request->boolean('send_welcome')) {
            $user->notify(new WelcomeUser(Password::broker()->createToken($user)));
        }

        AdminAlert::toAdmins("New user {$user->name} was added", route('admin.users.edit', $user), 'users.view', 'bi-person-plus');

        $redirect = $request->input('after') === 'another' ? route('admin.users.create') : route('admin.users.index');

        return redirect($redirect)->with('success', "User {$user->name} created".($request->boolean('send_welcome') ? ' · welcome email queued' : '').'.');
    }

    public function edit(User $user): View
    {
        $this->guardSuperAdmin($user);

        return view('users::admin.form', ['user' => $user->load('roles'), 'roles' => $this->roleOptions()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->guardSuperAdmin($user);

        $data = $this->userData($request, $user);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->update($data);
        if ($request->boolean('email_verified') !== (bool) $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => $request->boolean('email_verified') ? now() : null])->save();
        }
        $user->syncRoles([$request->validated('role')]);

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guardSuperAdmin($user);
        abort_if($user->is($request->user()), 422, 'You cannot delete your own account.');

        $user->delete();

        return back()->with('success', "User {$user->name} moved to the trash.");
    }

    public function restore(User $user): RedirectResponse
    {
        $this->guardSuperAdmin($user);
        $user->restore();

        return back()->with('success', "User {$user->name} restored.");
    }

    public function forceDelete(User $user): RedirectResponse
    {
        $this->guardSuperAdmin($user);
        if ($user->avatar) {
            Storage::disk('uploads')->delete($user->avatar);
        }
        $user->tokens()->delete();
        $user->forceDelete();

        return back()->with('success', "User {$user->name} deleted forever.");
    }

    /**
     * Activate, deactivate or delete several users at once.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete', 'restore', 'force-delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $inTrash = in_array($data['action'], ['restore', 'force-delete'], true);
        $users = User::query()->when($inTrash, fn ($q) => $q->onlyTrashed())
            ->whereKey($data['ids'])->whereKeyNot($request->user()->id)->get()
            ->reject(fn (User $u) => $u->isSuperAdmin() && ! $request->user()->isSuperAdmin());

        if (in_array($data['action'], ['delete', 'restore', 'force-delete'], true)) {
            abort_unless($request->user()->can('users.delete'), 403);
        }

        match ($data['action']) {
            'delete' => $users->each->delete(),
            'restore' => $users->each->restore(),
            'force-delete' => $users->each(function (User $u) {
                if ($u->avatar) {
                    Storage::disk('uploads')->delete($u->avatar);
                }
                $u->tokens()->delete();
                $u->forceDelete();
            }),
            default => $users->each->update(['status' => $data['action'] === 'activate' ? 'active' : 'inactive']),
        };

        return back()->with('success', $users->count().' user(s) updated.');
    }

    /**
     * "Import CSV" for users. Rows are matched by email: existing users are updated,
     * new users get a random password and must set their own (welcome email).
     *
     * @return CsvImport<User>
     */
    protected function importer(): CsvImport
    {
        $roles = array_keys($this->roleOptions());

        return CsvImport::make(User::class)
            ->matchBy('email')
            ->column('first_name', ['required', 'string', 'max:60'], example: 'Anita')
            ->column('last_name', ['nullable', 'string', 'max:60'], example: 'Raj')
            ->column('email', fn (?User $u) => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u)], example: 'anita@example.com')
            ->column('username', fn (?User $u) => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($u)], example: 'anita')
            ->column('phone', ['nullable', 'string', 'max:30'])
            ->column('role', ['required', Rule::in($roles)], example: 'editor')
            ->column('status', ['nullable', Rule::in(User::STATUSES)], example: 'active')
            ->column('department', ['nullable', 'string', 'max:100'])
            ->column('job_title', ['nullable', 'string', 'max:100'])
            ->using(function (array $data, ?User $user) {
                $role = $data['role'] ?? null;
                unset($data['role']);
                $data['email'] = strtolower($data['email'] ?? $user?->email);
                if (isset($data['username'])) {
                    $data['username'] = strtolower($data['username']);
                }
                $first = $data['first_name'] ?? $user?->first_name;
                $last = $data['last_name'] ?? $user?->last_name;
                $data['name'] = trim($first.' '.$last);

                if ($user) {
                    abort_if($user->isSuperAdmin() && ! auth()->user()?->isSuperAdmin(), 403);
                    $user->update($data);
                } else {
                    $user = User::query()->create($data + [
                        'status' => 'active',
                        'password' => Hash::make(Str::password(32)),
                        'force_password_reset' => true,
                    ]);
                    $user->notify(new WelcomeUser(Password::broker()->createToken($user)));
                }

                if ($role) {
                    $user->syncRoles([$role]);
                }
            });
    }

    /**
     * @return array{title: string, slug: string, back: string, breadcrumbs: array<int, string>, help: string}
     */
    protected function importPage(): array
    {
        return [
            'title' => 'Users', 'slug' => 'users', 'back' => route('admin.users.index'),
            'breadcrumbs' => ['Access control', 'Users', 'Import'],
            'help' => 'Rows are matched by <strong>email</strong>. New users get a welcome email with a link to set their password.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(UserRequest $request, ?User $user = null): array
    {
        $data = $request->safe()->except(['password', 'password_confirmation', 'role', 'email_verified', 'send_welcome', 'avatar', 'remove_avatar']);
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);

        if ($request->boolean('remove_avatar') && $user?->avatar) {
            Storage::disk('uploads')->delete($user->avatar);
            $data['avatar'] = null;
        }
        if ($request->hasFile('avatar')) {
            if ($user?->avatar) {
                Storage::disk('uploads')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'uploads');
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        $roles = Role::query()->orderBy('id')->get();
        if (! auth()->user()->isSuperAdmin()) {
            $roles = $roles->reject(fn ($r) => $r->name === config('starter.super_admin_role'));
        }

        return $roles->mapWithKeys(fn ($r) => [$r->name => $r->display_name ?: $r->name])->all();
    }

    /**
     * Only a super admin may edit or delete another super admin.
     */
    private function guardSuperAdmin(User $user): void
    {
        abort_if($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin(), 403, 'Only a super admin can change this account.');
    }
}
