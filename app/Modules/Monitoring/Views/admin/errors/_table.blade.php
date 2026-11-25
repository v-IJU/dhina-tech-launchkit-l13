{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
@php $where = ['staff' => 'Admin panel', 'customer' => 'Customer', 'api' => 'API', 'console' => 'Console / job']; @endphp
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.check-all :table="$table" />
            <x-admin.datatable.th :table="$table" sort="class">Error</x-admin.datatable.th>
            <th>Where</th>
            <x-admin.datatable.th :table="$table" sort="occurrences" class="text-end">Count</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="last_seen_at">Last seen</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="first_seen_at">First seen</x-admin.datatable.th>
            <th>Status</th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $error)
            <tr>
                <x-admin.datatable.check :table="$table" :value="$error->id" />
                <td style="max-width: 520px">
                    <a href="{{ route('admin.errors.show', $error) }}" class="fw-semibold text-body">{{ $error->shortClass() }}</a>
                    <div class="text-truncate">{{ $error->message ?: '(no message)' }}</div>
                    <small class="code text-secondary">{{ $error->location() }}</small>
                </td>
                <td style="max-width: 260px">
                    @if ($error->user_type)<span class="badge badge-soft-secondary">{{ $where[$error->user_type] ?? $error->user_type }}</span>@endif
                    <div class="small text-secondary text-truncate" title="{{ $error->url }}">{{ $error->method }} {{ $error->url ? \Illuminate\Support\Str::after($error->url, url('/')) ?: '/' : '' }}</div>
                </td>
                <td class="text-end fw-semibold">{{ number_format($error->occurrences) }}</td>
                <td class="text-secondary text-nowrap" title="{{ $error->last_seen_at }}">{{ $error->last_seen_at?->diffForHumans() }}</td>
                <td class="text-secondary text-nowrap">{{ $error->first_seen_at?->format('d M Y, H:i') }}</td>
                <td>
                    @if ($error->resolved_at)
                        <span class="badge badge-soft-success">Fixed</span>
                    @else
                        <span class="badge badge-soft-danger">Open</span>
                    @endif
                </td>
                <td class="actions">
                    <a href="{{ route('admin.errors.show', $error) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="View error" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                    @can('monitoring.manage')
                        <form method="POST" action="{{ route('admin.errors.resolve', $error) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-icon btn-outline-{{ $error->resolved_at ? 'secondary' : 'success' }}" aria-label="{{ $error->resolved_at ? 'Reopen' : 'Mark as fixed' }}" data-bs-toggle="tooltip" title="{{ $error->resolved_at ? 'Reopen' : 'Mark as fixed' }}"><i class="bi {{ $error->resolved_at ? 'bi-arrow-counterclockwise' : 'bi-check2' }}"></i></button>
                        </form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty icon="bi-emoji-smile" :message="$table->emptyMessage('errors')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="errors" />
