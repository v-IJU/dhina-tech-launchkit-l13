{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.th :table="$table" sort="created_at">When</x-admin.datatable.th>
            <th>Who</th>
            <x-admin.datatable.th :table="$table" sort="event">Event</x-admin.datatable.th>
            <th>What happened</th>
            <x-admin.datatable.th :table="$table" sort="log_name">Area</x-admin.datatable.th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $activity)
            <tr>
                <td class="text-nowrap text-secondary" title="{{ $activity->created_at }}">{{ $activity->created_at->format('d M Y, H:i') }}</td>
                <td>
                    @if ($activity->causer)
                        <div class="d-flex align-items-center gap-2"><x-admin.avatar :user="$activity->causer" size="sm" /> {{ $activity->causer->name }}</div>
                    @else
                        <span class="text-secondary">System</span>
                    @endif
                </td>
                <td>
                    <span class="badge {{ ['created' => 'badge-soft-success', 'updated' => 'badge-soft-primary', 'deleted' => 'badge-soft-danger', 'login' => 'badge-soft-info'][$activity->event] ?? 'badge-soft-secondary' }}">{{ \Illuminate\Support\Str::headline($activity->event ?: 'event') }}</span>
                </td>
                <td>
                    {{ $activity->description }}
                    @if ($activity->subject_type)
                        <span class="text-secondary small">· {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span>
                    @endif
                </td>
                <td><code class="text-secondary">{{ $activity->log_name }}</code></td>
                <td class="actions"><a href="{{ route('admin.activity.show', $activity) }}" class="btn btn-sm btn-outline-secondary">Details</a></td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty icon="bi-activity" :message="$table->isFiltered() ? 'No activity matches these filters.' : 'No activity yet.'" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="entries" />
