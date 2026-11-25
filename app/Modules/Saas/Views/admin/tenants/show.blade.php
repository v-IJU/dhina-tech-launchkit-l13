@extends('layouts.admin')

@php $latest = $usage->first(); @endphp

@section('content')
    <x-admin.page-header :title="$tenant->name" :breadcrumbs="['SaaS', 'Tenants' => route('admin.saas.tenants.index'), $tenant->name]">
        <a href="{{ route('admin.saas.tenants.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
        <a href="{{ $tenant->url() }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-globe me-1"></i> Website</a>
        @can('saas.edit')
            <form method="POST" action="{{ route('admin.saas.tenants.impersonate', $tenant) }}" class="d-inline" target="_blank">
                @csrf
                <button class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> Open as owner</button>
            </form>
            <a href="{{ route('admin.saas.tenants.edit', $tenant) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
        @endcan
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8 d-flex flex-column gap-4">
            <x-admin.card>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="stat-icon" style="background: #2563eb"><i class="bi bi-building"></i></span>
                    <div class="flex-fill">
                        <div class="fs-5 fw-semibold">{{ $tenant->name }}</div>
                        <div class="text-secondary small">{{ $tenant->email }} · ID <code>{{ $tenant->id }}</code> · database <code>{{ $tenant->database()->getName() }}</code></div>
                    </div>
                    @include('saas::admin._status', ['status' => $tenant->status])
                </div>
                <hr>
                <div class="row g-3 small">
                    <div class="col-sm-6 col-lg-3"><div class="text-secondary">Plan</div><div class="fw-semibold">{{ $tenant->plan?->name ?? '—' }}</div></div>
                    <div class="col-sm-6 col-lg-3"><div class="text-secondary">Signed up</div><div class="fw-semibold">{{ $tenant->created_at?->format('d M Y') }}</div></div>
                    <div class="col-sm-6 col-lg-3"><div class="text-secondary">{{ $tenant->status === 'trial' ? 'Trial ends' : 'Suspended' }}</div><div class="fw-semibold">{{ ($tenant->status === 'trial' ? $tenant->trial_ends_at : $tenant->suspended_at)?->format('d M Y') ?? '—' }}</div></div>
                    <div class="col-sm-6 col-lg-3"><div class="text-secondary">Owner</div><div class="fw-semibold">{{ $owner?->name ?? '—' }}</div><div class="text-secondary">last sign-in {{ $owner?->last_login_at?->diffForHumans() ?? 'never' }}</div></div>
                </div>
            </x-admin.card>

            <x-admin.card title="Usage" :subtitle="$latest ? 'Measured '.$latest->date->format('d M Y') : 'Not measured yet'">
                @can('saas.edit')
                    <x-slot:actions>
                        <form method="POST" action="{{ route('admin.saas.tenants.usage', $tenant) }}">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i> Measure now</button></form>
                    </x-slot:actions>
                @endcan
                <div class="row g-3">
                    @foreach (\App\Modules\Saas\Models\Plan::LIMITS + ['logins_30d' => 'Sign-ins (30 days)'] as $key => $label)
                        @php $used = (int) ($latest?->{$key} ?? 0); $limit = $key === 'logins_30d' ? null : $tenant->limit($key); @endphp
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-secondary small">{{ $label }}</div>
                            <div class="fs-5 fw-bold">{{ number_format($used) }} <small class="text-secondary fs-6 fw-normal">/ {{ $limit === null ? '∞' : number_format($limit) }}</small></div>
                            @if ($limit)
                                <div class="progress mt-1" style="height: 6px"><div class="progress-bar {{ $used >= $limit ? 'bg-danger' : ($used / $limit > .8 ? 'bg-warning' : '') }}" style="width: {{ min(100, $used / $limit * 100) }}%"></div></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-admin.card>

            <x-admin.card title="Subscriptions" flush>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Plan</th><th>Gateway</th><th>Status</th><th>Period</th><th class="text-end"></th></tr></thead>
                        <tbody>
                        @forelse ($tenant->subscriptions as $s)
                            <tr>
                                <td>{{ $s->plan?->name ?? '—' }} <small class="text-secondary">/ {{ $s->interval }}</small></td>
                                <td>{{ \App\Modules\Saas\Models\Subscription::GATEWAYS[$s->gateway] ?? $s->gateway }}<div class="code small text-secondary">{{ $s->gateway_id }}</div></td>
                                <td><span class="badge badge-soft-{{ ['active' => 'success', 'past_due' => 'warning', 'pending' => 'secondary', 'cancelled' => 'info', 'ended' => 'secondary'][$s->status] ?? 'secondary' }}">{{ \App\Modules\Saas\Models\Subscription::STATUSES[$s->status] ?? $s->status }}</span></td>
                                <td class="small text-secondary">{{ $s->current_period_start?->format('d M Y') }} – {{ $s->current_period_end?->format('d M Y') }}</td>
                                <td class="text-end">
                                    @if (in_array($s->status, ['active', 'past_due'], true))
                                        @can('saas.edit')
                                            <form method="POST" action="{{ route('admin.saas.subscriptions.cancel', $s) }}" data-confirm="Cancel this subscription?" data-confirm-body="It stops renewing. Access continues until the end of the paid period." data-confirm-button="Cancel subscription">
                                                @csrf <input type="hidden" name="at_period_end" value="1">
                                                <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="small text-secondary">No subscription yet{{ $tenant->status === 'trial' ? ' (on trial)' : '' }}.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>

            <x-admin.card title="Invoices" flush>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Number</th><th>Date</th><th>Description</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($tenant->invoices as $invoice)
                            <tr>
                                <td><a href="{{ route('admin.saas.invoices.show', $invoice) }}" class="code">{{ $invoice->number }}</a></td>
                                <td class="text-secondary small">{{ $invoice->created_at?->format('d M Y') }}</td>
                                <td class="small">{{ $invoice->description }}</td>
                                <td class="text-end fw-semibold">{{ $invoice->money() }}</td>
                                <td><span class="badge badge-soft-{{ ['paid' => 'success', 'open' => 'warning', 'void' => 'secondary'][$invoice->status] }}">{{ \App\Modules\Saas\Models\Invoice::STATUSES[$invoice->status] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="small text-secondary">No invoices yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4 d-flex flex-column gap-4">
            @can('saas.edit')
                <x-admin.card title="Record a payment" subtitle="Bank transfer, cheque, UPI to your account. Activates the plan and creates a paid invoice.">
                    <form method="POST" action="{{ route('admin.saas.tenants.payments.store', $tenant) }}" class="d-flex flex-column gap-3">
                        @csrf
                        <x-admin.form.select name="plan_id" label="Plan" :options="$plans->pluck('name', 'id')->all()" :value="$tenant->plan_id" />
                        <x-admin.form.select name="interval" label="Period" :options="['month' => 'One month', 'year' => 'One year']" value="month" />
                        <x-admin.form.input name="amount" type="number" label="Amount before tax" :value="\App\Modules\Saas\Support\Money::toMajor($tenant->plan?->price_monthly ?? 0)" hint="Tax from Settings → Billing is added on the invoice." />
                        <x-admin.form.input name="reference" label="Reference" placeholder="UTR / cheque no." />
                        <button class="btn btn-success">Record payment</button>
                    </form>
                </x-admin.card>

                <x-admin.card title="Account">
                    <div class="d-grid gap-2">
                        <form method="POST" action="{{ route('admin.saas.tenants.extend-trial', $tenant) }}" class="input-group">
                            @csrf
                            <input type="number" name="days" value="7" min="1" max="365" class="form-control" aria-label="Days">
                            <button class="btn btn-outline-secondary">Extend trial (days)</button>
                        </form>
                        <form method="POST" action="{{ route('admin.saas.tenants.suspend', $tenant) }}" class="d-grid"
                              data-confirm="{{ $tenant->isBlocked() ? 'Reactivate '.$tenant->name.'?' : 'Suspend '.$tenant->name.'?' }}"
                              data-confirm-body="{{ $tenant->isBlocked() ? 'Their panel and website work again.' : 'Their website and panel stop working. Only sign-in and Billing stay open so they can pay.' }}"
                              data-confirm-button="{{ $tenant->isBlocked() ? 'Reactivate' : 'Suspend' }}">
                            @csrf
                            <button class="btn {{ $tenant->isBlocked() ? 'btn-outline-success' : 'btn-outline-danger' }}">{{ $tenant->isBlocked() ? 'Reactivate' : 'Suspend' }}</button>
                        </form>
                    </div>
                </x-admin.card>
            @endcan

            <x-admin.card title="Domains" flush>
                @foreach ($tenant->domains as $domain)
                    <div class="d-flex align-items-center gap-2 px-4 py-2 border-top">
                        <a href="https://{{ $domain->host() }}" target="_blank" rel="noopener" class="code small flex-fill">{{ $domain->host() }}</a>
                        @if ($tenant->domains->count() > 1)
                            @can('saas.edit')
                                <x-admin.delete-button :action="route('admin.saas.tenants.domains.destroy', [$tenant, $domain])" confirm="Remove this domain?" body="The tenant stops opening on it." button="Remove" />
                            @endcan
                        @endif
                    </div>
                @endforeach
                @can('saas.edit')
                    <form method="POST" action="{{ route('admin.saas.tenants.domains.store', $tenant) }}" class="px-4 py-3 border-top">
                        @csrf
                        <div class="input-group input-group-sm">
                            <input name="domain" class="form-control @error('domain') is-invalid @enderror" placeholder="admin.theircompany.com" aria-label="Custom domain">
                            <button class="btn btn-outline-primary">Add</button>
                        </div>
                        @error('domain')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </form>
                @endcan
            </x-admin.card>

            <x-admin.card title="Modules">
                <div class="small text-secondary mb-2">Core modules + the plan's modules{{ $tenant->extra_modules ? ' + extras' : '' }}:</div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach ($modules as $slug => $module)
                        @php $on = in_array($slug, $tenant->moduleSlugs(), true); @endphp
                        <span class="badge {{ $on ? 'badge-soft-success' : 'badge-soft-secondary text-decoration-line-through' }}">{{ $module->name }}</span>
                    @endforeach
                </div>
            </x-admin.card>

            @if ($tenant->notes)
                <x-admin.card title="Staff notes"><p class="small mb-0" style="white-space: pre-line">{{ $tenant->notes }}</p></x-admin.card>
            @endif

            @can('saas.delete')
                <x-admin.card title="Delete tenant" subtitle="Deletes the tenant AND its database. Cannot be undone.">
                    <form method="POST" action="{{ route('admin.saas.tenants.destroy', $tenant) }}" class="d-flex flex-column gap-2">
                        @csrf @method('DELETE')
                        <input name="confirm" class="form-control @error('confirm') is-invalid @enderror" placeholder="Type {{ $tenant->id }} to confirm" aria-label="Confirm tenant ID">
                        @error('confirm')<div class="text-danger small">{{ $message }}</div>@enderror
                        <button class="btn btn-danger">Delete tenant and database</button>
                    </form>
                </x-admin.card>
            @endcan
        </div>
    </div>
@endsection
