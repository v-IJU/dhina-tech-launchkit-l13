{{-- Tenant status badge: @include('saas::admin._status', ['status' => $tenant->status]) --}}
@php $tones = ['trial' => 'info', 'active' => 'success', 'past_due' => 'warning', 'suspended' => 'danger', 'cancelled' => 'secondary']; @endphp
<span class="badge badge-soft-{{ $tones[$status] ?? 'secondary' }}">{{ \App\Modules\Saas\Models\Tenant::STATUSES[$status] ?? $status }}</span>
