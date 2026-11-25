{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
@php $badges = ['login' => 'badge-soft-success', 'failed' => 'badge-soft-danger', 'logout' => 'badge-soft-secondary']; @endphp
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.th :table="$table" sort="created_at">When</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="event">Event</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="email">Customer</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="ip_address">IP address</x-admin.datatable.th>
            <th>Device</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $login)
            <tr>
                <td class="text-secondary text-nowrap" title="{{ $login->created_at }}">{{ $login->created_at?->format('d M Y, H:i') }}<br><small>{{ $login->created_at?->diffForHumans() }}</small></td>
                <td><span class="badge {{ $badges[$login->event] ?? 'badge-soft-secondary' }}">{{ \App\Models\LoginEvent::EVENTS[$login->event] ?? $login->event }}</span></td>
                <td>
                    @if ($login->customer)
                        <div class="d-flex align-items-center gap-2">
                            <x-admin.avatar :user="$login->customer" size="sm" />
                            <div class="d-flex flex-column">
                                <a href="{{ route('admin.customers.show', $login->customer) }}" class="fw-semibold text-body">{{ $login->customer->name }}</a>
                                <small class="text-secondary">{{ $login->email }}</small>
                            </div>
                        </div>
                    @else
                        <span class="text-secondary">{{ $login->email ?: 'Unknown' }}</span> <small class="text-secondary">(no such customer)</small>
                    @endif
                </td>
                <td class="code text-secondary">{{ $login->ip_address }}</td>
                <td>
                    <i class="bi {{ in_array($login->platform, ['iPhone', 'Android', 'iPad'], true) ? 'bi-phone' : 'bi-laptop' }} text-secondary me-1"></i>{{ $login->device() }}
                    @if ($login->is_new_device)<span class="badge badge-soft-warning ms-1">New device</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty icon="bi-shield-lock" :message="$table->emptyMessage('sign-ins')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="events" />
