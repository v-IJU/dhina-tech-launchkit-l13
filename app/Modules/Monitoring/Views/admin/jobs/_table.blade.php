{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.check-all :table="$table" />
            <th>Job</th>
            <th>Error</th>
            <x-admin.datatable.th :table="$table" sort="queue">Queue</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="failed_at">Failed</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $job)
            <tr>
                <x-admin.datatable.check :table="$table" :value="$job->id" />
                <td>
                    <a href="{{ route('admin.failed-jobs.show', $job) }}" class="fw-semibold text-body">{{ class_basename($job->jobName()) }}</a>
                    <div class="small code text-secondary">{{ $job->jobName() }}</div>
                </td>
                <td style="max-width: 460px"><div class="small text-danger text-truncate" title="{{ $job->errorSummary() }}">{{ $job->errorSummary() }}</div></td>
                <td><span class="badge badge-soft-secondary">{{ $job->queue }}</span></td>
                <td class="text-secondary text-nowrap" title="{{ $job->failed_at }}">{{ $job->failed_at?->diffForHumans() }}</td>
                <td class="actions">
                    <a href="{{ route('admin.failed-jobs.show', $job) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="View job" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                    @can('monitoring.manage')
                        <form method="POST" action="{{ route('admin.failed-jobs.retry', $job) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-icon btn-outline-primary" aria-label="Retry" data-bs-toggle="tooltip" title="Retry"><i class="bi bi-arrow-repeat"></i></button>
                        </form>
                        <x-admin.delete-button :action="route('admin.failed-jobs.destroy', $job)" confirm="Delete this failed job?" body="It will not be retried." />
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty icon="bi-check2-circle" :message="$table->emptyMessage('failed jobs')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="jobs" />
