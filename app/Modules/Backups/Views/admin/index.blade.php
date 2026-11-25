@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Backups & system" :breadcrumbs="['System', 'Backups & system']">
        @can('backups.create')
            <form method="POST" action="{{ route('admin.backups.store') }}" class="d-flex gap-2">
                @csrf
                <button name="type" value="db" class="btn btn-outline-secondary"><i class="bi bi-database me-1"></i> Database only</button>
                <button name="type" value="full" class="btn btn-primary"><i class="bi bi-cloud-arrow-down me-1"></i> Full backup now</button>
            </form>
        @endcan
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="Backups" subtitle="spatie/laravel-backup · runs daily at 02:00 from the scheduler · old ones cleaned at 01:30" :flush="true">
                @if ($diskError)
                    <div class="alert alert-warning m-3">Backup disk not reachable: {{ $diskError }}</div>
                @endif
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>File</th><th>Size</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        @forelse ($backups as $backup)
                            <tr>
                                <td><code>{{ $backup['name'] }}</code></td>
                                <td class="text-secondary">{{ $backup['size'] }}</td>
                                <td class="text-secondary">{{ $backup['date']->format('d M Y, H:i') }} <small>({{ $backup['date']->diffForHumans() }})</small></td>
                                <td class="actions">
                                    @can('backups.download')
                                        <a href="{{ route('admin.backups.download', ['path' => $backup['path']]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Download</a>
                                    @endcan
                                    @can('backups.delete')
                                        <form method="POST" action="{{ route('admin.backups.destroy') }}" class="d-inline" data-confirm="Delete this backup?" data-confirm-button="Delete">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="path" value="{{ $backup['path'] }}">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-admin.empty icon="bi-database" message="No backups yet. Click “Full backup now” or wait for the nightly run." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
        <div class="col-xl-4 d-flex flex-column gap-4">
            <x-admin.card title="System information">
                <dl class="row small mb-0">
                    @foreach ($system as $label => $value)
                        <dt class="col-5 text-secondary fw-normal">{{ $label }}</dt>
                        <dd class="col-7"><code>{{ $value }}</code></dd>
                    @endforeach
                </dl>
            </x-admin.card>
            @can('backups.create')
                <x-admin.card title="Maintenance tools">
                    <div class="d-grid gap-2">
                        @foreach ($tools as $key => [$label, $command])
                            <form method="POST" action="{{ route('admin.backups.tool', $key) }}">
                                @csrf
                                <button class="btn btn-outline-secondary w-100 d-flex justify-content-between align-items-center"><span>{{ $label }}</span><code class="small">{{ $command }}</code></button>
                            </form>
                        @endforeach
                    </div>
                </x-admin.card>
            @endcan
        </div>
    </div>
@endsection
