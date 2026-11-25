<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <th>Tenant</th><th>Plan</th><th>Gateway</th>
            <x-admin.datatable.th :table="$table" sort="status">Status</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="current_period_end">Renews / ends</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="created_at">Started</x-admin.datatable.th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $s)
            <tr>
                <td>@if ($s->tenant)<a href="{{ route('admin.saas.tenants.show', $s->tenant) }}" class="fw-semibold text-body">{{ $s->tenant->name }}</a>@else — @endif</td>
                <td>{{ $s->plan?->name ?? '—' }} <small class="text-secondary">/ {{ $s->interval }}</small></td>
                <td>{{ \App\Modules\Saas\Models\Subscription::GATEWAYS[$s->gateway] ?? $s->gateway }}<div class="code small text-secondary">{{ $s->gateway_id }}</div></td>
                <td><span class="badge badge-soft-{{ ['active' => 'success', 'past_due' => 'warning', 'pending' => 'secondary', 'cancelled' => 'info', 'ended' => 'secondary'][$s->status] ?? 'secondary' }}">{{ \App\Modules\Saas\Models\Subscription::STATUSES[$s->status] ?? $s->status }}</span></td>
                <td class="text-secondary small">{{ $s->current_period_end?->format('d M Y') ?? '—' }}</td>
                <td class="text-secondary small">{{ $s->created_at?->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty icon="bi-arrow-repeat" :message="$table->emptyMessage('subscriptions')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="subscriptions" />
