{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.th :table="$table" sort="name">Tenant</x-admin.datatable.th>
            <th>Domain</th>
            <th>Plan</th>
            <x-admin.datatable.th :table="$table" sort="status">Status</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="trial_ends_at">Trial / renews</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="created_at">Signed up</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $tenant)
            <tr>
                <td>
                    <a href="{{ route('admin.saas.tenants.show', $tenant) }}" class="fw-semibold text-body">{{ $tenant->name }}</a>
                    <div class="small text-secondary">{{ $tenant->email }}</div>
                </td>
                <td><a href="{{ $tenant->url() }}" target="_blank" rel="noopener" class="code small">{{ $tenant->primaryDomain() }}</a></td>
                <td>{{ $tenant->plan?->name ?? '—' }}</td>
                <td>@include('saas::admin._status', ['status' => $tenant->status])</td>
                <td class="text-secondary text-nowrap small">
                    @if ($tenant->status === 'trial')
                        Trial ends {{ $tenant->trial_ends_at?->format('d M Y') }}
                    @elseif ($tenant->currentSubscription?->current_period_end)
                        {{ $tenant->currentSubscription->cancel_at_period_end ? 'Ends' : 'Renews' }} {{ $tenant->currentSubscription->current_period_end->format('d M Y') }}
                    @else
                        —
                    @endif
                </td>
                <td class="text-secondary text-nowrap">{{ $tenant->created_at?->format('d M Y') }}</td>
                <td class="actions">
                    @can('saas.edit')
                        <form method="POST" action="{{ route('admin.saas.tenants.impersonate', $tenant) }}" class="d-inline" target="_blank">
                            @csrf
                            <button class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Open {{ $tenant->name }} as owner" data-bs-toggle="tooltip" title="Open as owner"><i class="bi bi-box-arrow-up-right"></i></button>
                        </form>
                    @endcan
                    <a href="{{ route('admin.saas.tenants.show', $tenant) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="View {{ $tenant->name }}" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                </td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty icon="bi-buildings" :message="$table->emptyMessage('tenants')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="tenants" />
