@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Permissions" :breadcrumbs="['Access control', 'Permissions']" />

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="All permissions" subtitle="Created from each module.json by php artisan module:sync" :flush="true">
                <x-slot:actions><span class="badge badge-soft-primary">{{ $total }} permissions</span></x-slot:actions>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Module</th><th>Permissions</th><th>Roles with access</th></tr></thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ $row['label'] }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($row['permissions'] as $perm)
                                            <span class="badge code {{ $perm['custom'] ? 'badge-soft-warning' : 'badge-soft-primary' }} d-inline-flex align-items-center gap-1">
                                                {{ $perm['name'] }}
                                                @if ($perm['custom'] && $perm['id'])
                                                    @can('permissions.delete')
                                                        <form method="POST" action="{{ route('admin.permissions.destroy', $perm['id']) }}" class="d-inline" data-confirm="Remove {{ $perm['name'] }}?" data-confirm-body="It will be taken away from every role." data-confirm-button="Remove">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-link p-0 lh-1 text-reset" aria-label="Remove {{ $perm['name'] }}"><i class="bi bi-x"></i></button>
                                                        </form>
                                                    @endcan
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-secondary small">{{ $row['roles'] ?: 'Nobody' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
        <div class="col-xl-4 d-flex flex-column gap-4">
            @can('permissions.create')
                <form method="POST" action="{{ route('admin.permissions.store') }}">
                    @csrf
                    <x-admin.card title="Add custom permission">
                        <div class="d-flex flex-column gap-3">
                            <x-admin.form.select name="resource" label="Module" :options="$resources->all()" required />
                            <x-admin.form.input name="action" label="Action name" placeholder="publish" required hint="Creates <code>module.action</code>. Super admin gets it automatically." />
                        </div>
                        <x-slot:footer><button class="btn btn-primary">Create permission</button></x-slot:footer>
                    </x-admin.card>
                </form>
            @endcan
            <x-admin.card title="How permissions are checked">
                <ul class="small text-secondary mb-0 ps-3 d-flex flex-column gap-2">
                    <li>Routes: controllers use <code>new Middleware('permission:blog.edit')</code>.</li>
                    <li>Blade: wrap buttons in <code>@@can('blog.edit') … @@endcan</code>.</li>
                    <li>Code: <code>$user->can('blog.edit')</code>.</li>
                    <li>The sidebar hides menu items a role cannot view.</li>
                    <li>The <code>super-admin</code> role passes every check.</li>
                </ul>
            </x-admin.card>
        </div>
    </div>
@endsection
