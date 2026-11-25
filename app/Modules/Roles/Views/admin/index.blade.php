@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Roles" :breadcrumbs="['Access control', 'Roles']">
        @can('roles.create')
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create role</a>
        @endcan
    </x-admin.page-header>

    <div class="row g-3">
        @foreach ($roles as $role)
            @php
                $isSuper = $role->name === config('starter.super_admin_role');
                $count = $isSuper ? $total : $role->permissions_count;
                $system = in_array($role->name, config('starter.system_roles'), true);
            @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-flex align-items-center justify-content-center rounded-3 text-white" style="width: 44px; height: 44px; background: {{ $role->color }}"><i class="bi bi-shield-lock fs-5"></i></span>
                            <div class="flex-fill">
                                <div class="fw-bold fs-6">{{ $role->display_name ?: $role->name }}</div>
                                <code class="text-secondary">{{ $role->name }}</code>
                            </div>
                            @if ($system)<span class="badge badge-soft-secondary">System</span>@endif
                        </div>
                        <p class="text-secondary small mb-0 flex-fill">{{ $role->description ?: 'No description.' }}</p>
                        <div class="d-flex gap-4">
                            <div><div class="fs-5 fw-bold">{{ $role->users_count }}</div><small class="text-secondary">users</small></div>
                            <div><div class="fs-5 fw-bold">{{ $isSuper ? 'All' : $count }}</div><small class="text-secondary">permissions</small></div>
                        </div>
                        <div class="progress" style="height: 6px" role="progressbar" aria-label="Share of permissions" aria-valuenow="{{ $count }}" aria-valuemin="0" aria-valuemax="{{ $total }}">
                            <div class="progress-bar" style="width: {{ $total ? round($count / $total * 100) : 0 }}%; background: {{ $role->color }}"></div>
                        </div>
                        <div class="d-flex">
                            @foreach ($role->users as $member)
                                <x-admin.avatar :user="$member" size="sm" class="border border-2 border-white" style="margin-right: -6px" />
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        @can('roles.create')
                            <a href="{{ route('admin.roles.create', ['from' => $role->name]) }}" class="btn btn-sm btn-outline-secondary">Duplicate</a>
                        @endcan
                        <div class="d-flex gap-2 ms-auto">
                            @if (! $system)
                                @can('roles.delete')
                                    <x-admin.delete-button :action="route('admin.roles.destroy', $role)" :confirm="'Delete role '.($role->display_name ?: $role->name).'?'" body="Its permissions are removed. Users must be moved to another role first." label="Delete" button="Delete role" />
                                @endcan
                            @endif
                            @can('roles.edit')
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-primary">{{ $isSuper ? 'View' : 'Edit permissions' }}</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        @can('roles.create')
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('admin.roles.create') }}" class="card h-100 text-decoration-none d-flex flex-column align-items-center justify-content-center gap-2 text-secondary" style="min-height: 260px; border: 2px dashed var(--bs-border-color)">
                    <span class="d-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width: 48px; height: 48px"><i class="bi bi-plus-lg fs-4"></i></span>
                    <span class="fw-semibold text-body">Create new role</span>
                    <small>Pick a name and tick the permissions it needs</small>
                </a>
            </div>
        @endcan
    </div>
@endsection
