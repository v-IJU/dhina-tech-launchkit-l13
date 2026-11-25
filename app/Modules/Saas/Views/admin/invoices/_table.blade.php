<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.th :table="$table" sort="number">Number</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="created_at">Date</x-admin.datatable.th>
            <th>Tenant</th><th>Description</th>
            <x-admin.datatable.th :table="$table" sort="total" class="text-end">Total</x-admin.datatable.th>
            <th>Status</th><th>Gateway</th><th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $invoice)
            <tr>
                <td><a href="{{ route('admin.saas.invoices.show', $invoice) }}" class="code fw-semibold">{{ $invoice->number }}</a></td>
                <td class="text-secondary small text-nowrap">{{ $invoice->created_at?->format('d M Y') }}</td>
                <td>@if ($invoice->tenant)<a href="{{ route('admin.saas.tenants.show', $invoice->tenant) }}" class="text-body">{{ $invoice->tenant->name }}</a>@else {{ $invoice->billing['name'] ?? '—' }} @endif</td>
                <td class="small">{{ $invoice->description }}</td>
                <td class="text-end fw-semibold text-nowrap">{{ $invoice->money() }}</td>
                <td><span class="badge badge-soft-{{ ['paid' => 'success', 'open' => 'warning', 'void' => 'secondary'][$invoice->status] }}">{{ \App\Modules\Saas\Models\Invoice::STATUSES[$invoice->status] }}</span></td>
                <td class="small">{{ ucfirst((string) $invoice->gateway) }}<div class="code text-secondary">{{ $invoice->gateway_payment_id }}</div></td>
                <td class="actions">
                    <a href="{{ route('admin.saas.invoices.show', $invoice) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="View invoice" data-bs-toggle="tooltip" title="View / print"><i class="bi bi-printer"></i></a>
                    @can('saas.edit')
                        @if ($invoice->status === 'open')
                            <form method="POST" action="{{ route('admin.saas.invoices.paid', $invoice) }}" class="d-inline">@csrf<button class="btn btn-sm btn-icon btn-outline-success" aria-label="Mark paid" data-bs-toggle="tooltip" title="Mark paid"><i class="bi bi-check2"></i></button></form>
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty icon="bi-receipt" :message="$table->emptyMessage('invoices')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="invoices" />
