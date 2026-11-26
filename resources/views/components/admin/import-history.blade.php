{{--
    Recent imports with status, progress and counts. Used on every module's import page:
        <x-admin.import-history :runs="$runs" />
    Running background imports update themselves every 3 seconds (resources/admin/js/imports.js).
--}}
@props(['runs'])

<x-admin.card title="Recent imports" :flush="true" {{ $attributes }}>
    @if ($runs->isEmpty())
        <div class="p-4 text-center text-secondary small">No imports yet.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>File</th><th>By</th><th style="min-width: 220px">Progress</th><th>Result</th><th></th></tr></thead>
                <tbody>
                @foreach ($runs as $run)
                    @php
                        $badge = ['done' => 'success', 'failed' => 'danger', 'running' => 'primary', 'pending' => 'secondary'][$run->status] ?? 'secondary';
                        $live = in_array($run->status, ['pending', 'running'], true);
                    @endphp
                    <tr @if ($live) data-import-run="{{ route('admin.imports.show', $run) }}" @endif>
                        <td>
                            <a href="{{ route('admin.imports.show', $run) }}" class="fw-semibold text-decoration-none">{{ \Illuminate\Support\Str::limit($run->file_name, 40) }}</a>
                            <div class="small text-secondary">
                                {{ $run->created_at->diffForHumans() }} · {{ number_format($run->total_rows) }} rows
                                @if ($run->dry_run)<span class="badge badge-soft-warning ms-1">Check only</span>@endif
                                @if ($run->queued)<span class="badge badge-soft-info ms-1">Background</span>@endif
                            </div>
                        </td>
                        <td class="small">{{ $run->user?->name ?? '-' }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-fill" style="height: 8px"><div class="progress-bar bg-{{ $badge }}" data-import-bar style="width: {{ $run->percent() }}%"></div></div>
                                <span class="badge bg-{{ $badge }}" data-import-status>{{ ucfirst($run->status) }}</span>
                            </div>
                        </td>
                        <td class="small" data-import-summary>
                            {{ $run->summary() }}
                            @if ($run->message)<div class="text-danger">{{ \Illuminate\Support\Str::limit($run->message, 140) }}</div>@endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($run->failed_rows)
                                <a href="{{ route('admin.imports.errors', $run) }}" class="btn btn-sm btn-outline-danger" title="Download the skipped rows with the reason, fix them, import again"><i class="bi bi-download"></i> Skipped rows</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin.card>
