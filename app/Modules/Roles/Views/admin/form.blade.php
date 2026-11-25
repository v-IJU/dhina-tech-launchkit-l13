@extends('layouts.admin')

@php $editing = $role->exists; @endphp

@section('content')
    <x-admin.page-header :title="$editing ? 'Edit role' : 'Create role'" :breadcrumbs="['Access control', 'Roles' => route('admin.roles.index'), $editing ? 'Edit' : 'Create']">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="row g-4">
        <div class="col-xl-9 d-flex flex-column gap-4">

        <x-admin.card title="Role details" subtitle="The code name is what you check in code, e.g. hasRole('content-manager').">
            <div class="row g-3">
                <div class="col-md-6">
                    <x-admin.form.input name="display_name" label="Role name" :value="$role->display_name ?: $role->name" required placeholder="Content manager" />
                    <div class="form-text">Code name: <code>{{ $role->name ?: 'generated from the name' }}</code></div>
                </div>
                @unless ($locked)
                    <div class="col-md-6">
                        <label class="form-label" for="copyFrom">Start from an existing role</label>
                        <select id="copyFrom" class="form-select" data-copy-role>
                            <option value="">Choose a role to copy its permissions…</option>
                            @foreach ($copyFrom as $option)
                                <option value="{{ $option['name'] }}" data-permissions='@json($option['permissions'])'>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <x-admin.form.input class="col-12" name="description" label="Description" :value="$role->description" placeholder="What can people with this role do?" />
                <div class="col-md-6">
                    <span class="form-label d-block">Badge colour</span>
                    <div class="d-flex gap-2">
                        @foreach ($colors as $color)
                            <input type="radio" class="btn-check" name="color" id="color_{{ $loop->index }}" value="{{ $color }}" @checked(old('color', $role->color) === $color)>
                            <label class="swatch" for="color_{{ $loop->index }}" style="background: {{ $color }}"><span class="visually-hidden">Colour {{ $color }}</span></label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-6 d-flex align-items-end gap-3">
                    <div><small class="text-secondary d-block">Permissions selected</small><span class="fs-4 fw-bold"><span data-permission-count>{{ count($checked) }}</span> <small class="text-secondary fs-6 fw-normal">/ {{ $total }}</small></span></div>
                    @unless ($locked)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all="1">Select all</button>
                        <button type="button" class="btn btn-sm btn-link text-secondary" data-select-all="0">Clear all</button>
                    @endunless
                </div>
            </div>
        </x-admin.card>

        @if ($locked)
            <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i> The super admin role passes every permission check automatically, so its permissions cannot be changed.</div>
        @endif

        <fieldset @disabled($locked) class="d-flex flex-column gap-4" data-permission-matrix>
            <x-admin.card title="Module permissions" subtitle="Tick a column header to grant that action on every module." :flush="true">
                <div class="table-responsive">
                    <table class="table align-middle permission-matrix">
                        <thead>
                        <tr>
                            <th>Module</th>
                            @foreach ($actions as $action)
                                <th><label class="d-flex align-items-center gap-2" style="cursor: pointer"><input type="checkbox" class="form-check-input m-0" data-col-toggle="{{ $action }}" aria-label="Grant {{ $action }} on every module"> {{ ucfirst($action) }}</label></th>
                            @endforeach
                            <th>All</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($resources as $resource)
                            <tr data-row>
                                <td><div class="fw-semibold">{{ $resource['label'] }}</div><code class="text-secondary">{{ $resource['key'] }}.*</code></td>
                                @foreach ($actions as $action)
                                    <td>
                                        @if (in_array($action, $resource['actions'], true))
                                            @php $perm = $resource['key'].'.'.$action; @endphp
                                            <input type="checkbox" class="form-check-input m-0" name="permissions[]" value="{{ $perm }}" data-action="{{ $action }}"
                                                   @checked(in_array($perm, old('permissions', $checked), true)) aria-label="{{ $perm }}">
                                        @else
                                            <span class="text-secondary">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td><input type="checkbox" class="form-check-input m-0" data-row-toggle aria-label="All permissions for {{ $resource['label'] }}"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>

            @if ($special->isNotEmpty())
                <x-admin.card title="Special permissions" subtitle="Extra abilities beyond view / create / edit / delete. Add your own under Permissions.">
                    <div class="row g-2">
                        @foreach ($special as $perm)
                            <div class="col-md-6 col-xl-4">
                                <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2" style="cursor: pointer">
                                    <input type="checkbox" class="form-check-input m-0" name="permissions[]" value="{{ $perm }}" data-action="special" @checked(in_array($perm, old('permissions', $checked), true))>
                                    <code>{{ $perm }}</code>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>
            @endif
        </fieldset>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create role' }}</button>
        </div>
        </div>

        {{-- Live preview: updates as you tick boxes (resources/admin/js/role-matrix.js) --}}
        <div class="col-xl-3">
            <div class="card sticky-xl-top" style="top: 80px" data-sidebar-preview data-all="{{ $locked ? '1' : '0' }}">
                <div class="card-header">
                    <div class="fw-semibold">Sidebar for this role</div>
                    <small class="text-secondary">What people with this role will see. Updates as you tick boxes.</small>
                </div>
                <div class="card-body small" data-sidebar-preview-body style="max-height: calc(100vh - 200px); overflow: auto"></div>
                <script type="application/json" data-sidebar-tree nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json($menuTree)</script>
            </div>
        </div>
        </div>
    </form>
@endsection
