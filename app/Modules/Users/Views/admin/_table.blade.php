{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.check-all :table="$table" />
            <x-admin.datatable.th :table="$table" sort="name">User</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="username">Username</x-admin.datatable.th>
            <th>Role</th>
            <x-admin.datatable.th :table="$table" sort="status">Status</x-admin.datatable.th>
            <th>2FA</th>
            <x-admin.datatable.th :table="$table" sort="last_login_at">Last login</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="created_at">Joined</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $user)
            @php $protected = $user->is(auth()->user()) || ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()); @endphp
            <tr>
                <x-admin.datatable.check :table="$table" :value="$user->id" :disabled="$protected" />
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <x-admin.avatar :user="$user" />
                        <div class="d-flex flex-column">
                            <span class="fw-semibold">{{ $user->name }} @if ($user->is(auth()->user()))<span class="badge badge-soft-primary ms-1">You</span>@endif</span>
                            <small class="text-secondary">{{ $user->email }}</small>
                        </div>
                    </div>
                </td>
                <td class="text-secondary code">{{ $user->username ? '@'.$user->username : '—' }}</td>
                <td>
                    @foreach ($user->roles as $role)
                        <span class="badge badge-soft-secondary"><span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background: {{ $role->color }}"></span>{{ $role->display_name ?: $role->name }}</span>
                    @endforeach
                    @if ($user->permissions->isNotEmpty())
                        {{-- Extra permissions given to this user only (Users > Permissions) --}}
                        <span class="badge badge-soft-primary" title="{{ $user->permissions->pluck('name')->implode(', ') }}">+{{ $user->permissions->count() }} extra</span>
                    @endif
                </td>
                <td><x-admin.status-badge :status="$user->status" /></td>
                <td>
                    @if ($user->two_factor_confirmed_at)
                        <span class="badge badge-soft-success">Enabled</span>
                    @elseif ($user->require_two_factor)
                        <span class="badge badge-soft-warning">Required</span>
                    @else
                        <span class="badge badge-soft-secondary">Off</span>
                    @endif
                </td>
                <td class="text-secondary text-nowrap">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td class="text-secondary text-nowrap">{{ $user->created_at?->format('d M Y') }}</td>
                <td class="actions">
                    @if ($table->inTrash())
                        @if (! $protected)
                            @can('users.delete')
                                <x-admin.restore-button :action="route('admin.users.restore', $user)" />
                                <x-admin.delete-button :action="route('admin.users.force-delete', $user)" :confirm="'Delete '.$user->name.' forever?'" body="Their account and profile are removed for good. Content they created stays." button="Delete forever" />
                            @endcan
                        @endif
                    @else
                    @can('users.permissions')
                        @if (! $user->isSuperAdmin() && ! $user->is(auth()->user()))
                            <a href="{{ route('admin.users.permissions', $user) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Permissions for {{ $user->name }}" data-bs-toggle="tooltip" title="Extra permissions"><i class="bi bi-key"></i></a>
                        @endif
                    @endcan
                    @can('users.impersonate')
                        @if (! $user->isSuperAdmin() && ! $user->is(auth()->user()) && ! session('impersonator_id'))
                            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="d-inline" data-confirm="Sign in as {{ $user->name }}?" data-confirm-body="You will see the panel exactly as they do. Use “Return to my account” in the orange bar to come back." data-confirm-button="Sign in as user">
                                @csrf
                                <button class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Sign in as {{ $user->name }}" data-bs-toggle="tooltip" title="Sign in as"><i class="bi bi-person-badge"></i></button>
                            </form>
                        @endif
                    @endcan
                    @if (! $protected || $user->is(auth()->user()))
                        @can('users.edit')
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Edit {{ $user->name }}" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                        @endcan
                    @endif
                    @if (! $protected)
                        @can('users.delete')
                            <x-admin.delete-button :action="route('admin.users.destroy', $user)" :confirm="'Delete '.$user->name.'?'" body="The user loses access immediately and goes to the Trash, where they can be restored." button="Delete user" />
                        @endcan
                    @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><x-admin.empty :icon="$table->inTrash() ? 'bi-trash3' : 'bi-people'" :message="$table->emptyMessage('users')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="users" />
