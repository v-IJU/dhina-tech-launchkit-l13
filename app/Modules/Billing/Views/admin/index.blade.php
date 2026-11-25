@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Billing" :breadcrumbs="['System', 'Billing']">
        @can('billing.manage')
            <a href="{{ route('admin.billing.plans') }}" class="btn btn-primary"><i class="bi bi-stars me-1"></i> {{ $subscription && $subscription->isLive() ? 'Change plan' : 'Choose a plan' }}</a>
        @endcan
    </x-admin.page-header>

    @if ($tenant->status === 'trial')
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-hourglass-split"></i>
            <div class="flex-fill">You're on a free trial of <strong>{{ $tenant->plan?->name }}</strong>. {{ $tenant->trialDaysLeft() }} {{ \Illuminate\Support\Str::plural('day', $tenant->trialDaysLeft()) }} left (ends {{ $tenant->trial_ends_at?->format('d M Y') }}).</div>
        </div>
    @elseif ($tenant->status === 'past_due')
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> Your last payment failed. Please pay within {{ config('starter.tenancy.grace_days') }} days to avoid suspension.</div>
    @elseif ($tenant->isBlocked())
        <div class="alert alert-danger"><i class="bi bi-lock me-1"></i> Your account is suspended. Choose a plan and pay to unlock everything straight away.</div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8 d-flex flex-column gap-4">
            <x-admin.card title="Current plan">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="flex-fill">
                        <div class="fs-4 fw-bold">{{ $tenant->plan?->name ?? 'No plan' }}</div>
                        <div class="text-secondary">
                            @if ($subscription && $subscription->isLive())
                                {{ $subscription->plan?->formattedPrice($subscription->interval) }} / {{ $subscription->interval }} via {{ \App\Modules\Saas\Models\Subscription::GATEWAYS[$subscription->gateway] ?? $subscription->gateway }}
                                · {{ $subscription->cancel_at_period_end || $subscription->status === 'cancelled' ? 'ends' : 'renews' }} {{ $subscription->current_period_end?->format('d M Y') }}
                            @else
                                {{ $tenant->plan?->formattedPrice() }} / month
                            @endif
                        </div>
                    </div>
                    <span class="badge badge-soft-{{ ['trial' => 'info', 'active' => 'success', 'past_due' => 'warning'][$tenant->status] ?? 'danger' }} fs-6">{{ \App\Modules\Saas\Models\Tenant::STATUSES[$tenant->status] ?? $tenant->status }}</span>
                </div>
            </x-admin.card>

            <x-admin.card title="Usage">
                <div class="row g-4">
                    @foreach ($usage as $key => [$used, $limit])
                        <div class="col-md-4">
                            <div class="text-secondary small">{{ \App\Modules\Saas\Models\Plan::LIMITS[$key] }}</div>
                            <div class="fs-5 fw-bold">{{ number_format($used) }} <small class="text-secondary fw-normal fs-6">/ {{ $limit === null ? 'Unlimited' : number_format($limit) }}</small></div>
                            @if ($limit)
                                <div class="progress mt-1" style="height: 6px"><div class="progress-bar {{ $used >= $limit ? 'bg-danger' : ($used / $limit > .8 ? 'bg-warning' : '') }}" style="width: {{ min(100, $used / $limit * 100) }}%"></div></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-admin.card>

            <x-admin.card title="Invoices" flush>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Number</th><th>Date</th><th>Description</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="code">{{ $invoice->number }}</td>
                                <td class="small text-secondary">{{ $invoice->created_at?->format('d M Y') }}</td>
                                <td class="small">{{ $invoice->description }}</td>
                                <td class="text-end fw-semibold">{{ $invoice->money() }}</td>
                                <td><span class="badge badge-soft-{{ ['paid' => 'success', 'open' => 'warning', 'void' => 'secondary'][$invoice->status] }}">{{ \App\Modules\Saas\Models\Invoice::STATUSES[$invoice->status] }}</span></td>
                                <td class="text-end"><a href="{{ route('admin.billing.invoice', $invoice->id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="small text-secondary">No invoices yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4 d-flex flex-column gap-4">
            <x-admin.card title="Included modules">
                <div class="d-flex flex-wrap gap-1">
                    @foreach (app(\App\Core\Modules\ModuleManager::class)->planModules() as $slug => $module)
                        <span class="badge {{ in_array($slug, $tenant->moduleSlugs(), true) ? 'badge-soft-success' : 'badge-soft-secondary text-decoration-line-through' }}">{{ $module->name }}</span>
                    @endforeach
                </div>
            </x-admin.card>

            @if ($subscription && $subscription->status === 'active' && ! $subscription->cancel_at_period_end)
                @can('billing.manage')
                    <x-admin.card title="Cancel subscription" subtitle="Stops renewing. Everything works until the end of the paid period.">
                        <form method="POST" action="{{ route('admin.billing.cancel') }}" data-confirm="Cancel your subscription?" data-confirm-body="It will not renew. You keep access until {{ $subscription->current_period_end?->format('d M Y') }}." data-confirm-button="Cancel subscription">
                            @csrf
                            <button class="btn btn-outline-danger w-100">Cancel subscription</button>
                        </form>
                    </x-admin.card>
                @endcan
            @endif

            <x-admin.card title="Need help?">
                <p class="small text-secondary mb-0">For bank transfer, a custom plan or invoice changes, write to {{ \App\Core\Tenancy\Tenancy::central(fn () => setting('contact_email')) ?: 'us' }}.</p>
            </x-admin.card>
        </div>
    </div>
@endsection
