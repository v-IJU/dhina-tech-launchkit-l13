<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Export\TemplateExport;
use App\Core\Import\ImportRun;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Modules\Users\Exports\UsersExport;
use App\Modules\Users\Imports\UsersImport;
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
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller implements HasMiddleware
{
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
            ->export('users', UsersExport::class, 'users.export')   // app/Modules/Users/Exports/UsersExport.php
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
            ->filter(fn (User $u) => $request->user()->canManage($u));

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
        /** @var User $me */
        $me = auth()->user();
        // Only roles with no more permissions than the person giving them.
        $roles = Role::query()->with('permissions')->orderBy('id')->get()->filter(fn (Role $r) => $me->canGiveRole($r));

        return $roles->mapWithKeys(fn ($r) => [$r->name => $r->display_name ?: $r->name])->all();
    }

    /**
     * Nobody may change an account that has permissions they don't have (super admins: only by a super admin).
     */
    private function guardSuperAdmin(User $user): void
    {
        /** @var User $me */
        $me = auth()->user();
        abort_unless($me->canManage($user), 403, $user->isSuperAdmin()
            ? 'Only a super admin can change this account.'
            : 'This account has permissions you don\'t have, so you can\'t change it.');
    }

    /*
    | Import (Excel / CSV). Columns and saving: app/Modules/Users/Imports/UsersImport.php
    */
    public function importForm(): View
    {
        return view('users::admin.import', [
            'columns' => (new UsersImport)->describe(),
            'runs' => ImportRun::recent('users'),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ImportRun::fileRules(), 'dry_run' => ['nullable', 'boolean']]);

        $run = ImportRun::start('users', $request->file('file'), $request->user(), dryRun: $request->boolean('dry_run'));
        $run->process(new UsersImport($run));   // small file: now · big file: queued in chunks

        return redirect()->route('admin.users.import')->with(
            $run->status === 'failed' ? 'error' : 'success',
            match (true) {
                $run->status === 'failed' => 'The file could not be imported: '.$run->message,
                $run->queued => 'Import started in the background ('.number_format($run->total_rows).' rows). You will get a notification when it is done.',
                $run->dry_run => 'Check finished, nothing was saved: '.$run->summary().'.',
                default => 'Import finished: '.$run->summary().'.',
            },
        );
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new TemplateExport(new UsersImport), 'users-import-template.xlsx');
    }
}
