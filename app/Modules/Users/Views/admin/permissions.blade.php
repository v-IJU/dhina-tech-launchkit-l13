@extends('layouts.admin')

@php
    $cell = function (string $perm) use ($fromRoles, $extras, $grantable, $locked) {
        return [
            'role' => in_array($perm, $fromRoles, true),
            'extra' => in_array($perm, old('permissions', $extras), true),
            'can' => ! $locked && in_array($perm, $grantable, true),
        ];
    };
@endphp

@section('content')
    <x-admin.page-header :title="'Permissions: '.$user->name" :breadcrumbs="['Access control', 'Users' => route('admin.users.index'), $user->name => route('admin.users.edit', $user), 'Permissions']">
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to user</a>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-9 d-flex flex-column gap-4">
            <x-admin.card>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <x-admin.avatar :user="$user" />
                    <div class="flex-fill">
                        <div class="fw-semibold">{{ $user->name }}</div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @forelse ($user->roles as $role)
                                <span class="badge badge-soft-secondary"><span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background: {{ $role->color }}"></span>{{ $role->display_name ?: $role->name }}</span>
                            @empty
                                <span class="text-secondary small">No role</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="text-end small">
                        <div><span class="fw-semibold">{{ count($fromRoles) }}</span> <span class="text-secondary">from roles</span></div>
                        <div><span class="fw-semibold text-primary">{{ count($extras) }}</span> <span class="text-secondary">extra for this user</span></div>
                    </div>
                </div>
            </x-admin.card>

            @if ($user->isSuperAdmin())
                <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i> {{ $user->name }} is a super admin and passes every permission check, so extra permissions are not needed.</div>
            @elseif ($user->is(auth()->user()))
                <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock me-1"></i> You can't change your own permissions. Ask another administrator.</div>
            @else
                <div class="alert alert-light border small mb-0 d-flex gap-2">
                    <i class="bi bi-info-circle mt-1"></i>
                    <span>
                        <span class="badge badge-soft-secondary"><i class="bi bi-lock-fill me-1"></i>Role</span> = comes from a role; change it under <a href="{{ route('admin.roles.index') }}">Roles</a> (affects everyone with that role).
                        Tick the other boxes to give <strong>only {{ $user->name }}</strong> an extra permission. You can only grant permissions you have yourself.
                    </span>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.users.permissions.update', $user) }}" class="d-flex flex-column gap-4">
                @csrf @method('PUT')

                <x-admin.card title="Module permissions" :flush="true">
                    <div class="table-responsive">
                        <table class="table align-middle permission-matrix mb-0">
                            <thead>
                            <tr>
                                <th>Module</th>
                                @foreach ($actions as $action)<th>{{ ucfirst($action) }}</th>@endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($resources as $resource)
                                <tr>
                                    <td><div class="fw-semibold">{{ $resource['label'] }}</div><code class="text-secondary">{{ $resource['key'] }}.*</code></td>
                                    @foreach ($actions as $action)
                                        <td>
                                            @if (in_array($action, $resource['actions'], true))
                                                @php $perm = $resource['key'].'.'.$action; $c = $cell($perm); @endphp
                                                @if ($c['role'])
                                                    <span class="badge badge-soft-secondary" title="{{ $perm }}: from a role"><i class="bi bi-lock-fill me-1"></i>Role</span>
                                                @else
                                                    <input type="checkbox" class="form-check-input m-0" name="permissions[]" value="{{ $perm }}" aria-label="{{ $perm }}"
                                                           @checked($c['extra']) @disabled(! $c['can'])
                                                           @if (! $c['can'] && ! $locked) title="You don't have {{ $perm }} yourself" @endif>
                                                @endif
                                            @else
                                                <span class="text-secondary">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin.card>

                @if ($special->isNotEmpty())
                    <x-admin.card title="Special permissions">
                        <div class="row g-2">
                            @foreach ($special as $perm)
                                @php $c = $cell($perm); @endphp
                                <div class="col-md-6 col-xl-4">
                                    <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2">
                                        @if ($c['role'])
                                            <span class="badge badge-soft-secondary"><i class="bi bi-lock-fill me-1"></i>Role</span>
                                        @else
                                            <input type="checkbox" class="form-check-input m-0" name="permissions[]" value="{{ $perm }}" @checked($c['extra']) @disabled(! $c['can'])>
                                        @endif
                                        <code>{{ $perm }}</code>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </x-admin.card>
                @endif

                @unless ($locked)
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary">Save extra permissions</button>
                    </div>
                @endunless
            </form>
        </div>

        <div class="col-xl-3">
            <x-admin.card title="Their sidebar" subtitle="What {{ $user->name }} sees now (after saving).">
                @forelse ($sidebar as $group)
                    <div class="text-uppercase small text-secondary fw-semibold mt-2 mb-1">{{ $group['title'] }}</div>
                    <ul class="list-unstyled small mb-2">
                        @foreach ($group['items'] as $item)
                            <li class="py-1"><i class="bi {{ $item['icon'] }} me-2 text-secondary"></i>{{ $item['label'] }}
                                @if ($item['children'])
                                    <ul class="list-unstyled ms-4 text-secondary">
                                        @foreach ($item['children'] as $child)<li>{{ $child['label'] }}</li>@endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @empty
                    <p class="small text-secondary mb-0">Nothing: this user only sees their profile.</p>
                @endforelse
            </x-admin.card>
        </div>
    </div>
@endsection
