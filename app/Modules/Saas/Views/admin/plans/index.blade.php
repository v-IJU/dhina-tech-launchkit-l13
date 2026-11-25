@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Plans" :breadcrumbs="['SaaS', 'Plans']">
        <a href="{{ route('saas.pricing') }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> Pricing page</a>
        @can('plans.create')
            <a href="{{ route('admin.saas.plans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add plan</a>
        @endcan
    </x-admin.page-header>

    <div class="row g-4">
        @forelse ($plans as $plan)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 {{ $plan->is_featured ? 'border-primary' : '' }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start gap-2">
                            <div class="flex-fill">
                                <h2 class="h5 mb-0">{{ $plan->name }}</h2>
                                <code class="small text-secondary">{{ $plan->slug }}</code>
                            </div>
                            @unless ($plan->is_active)<span class="badge badge-soft-secondary">Not available</span>@endunless
                            @if ($plan->is_active && ! $plan->is_public)<span class="badge badge-soft-info">Hidden</span>@endif
                            @if ($plan->is_featured)<span class="badge badge-soft-primary">Featured</span>@endif
                        </div>
                        <div class="my-3"><span class="fs-3 fw-bold">{{ $plan->formattedPrice() }}</span><span class="text-secondary">/month</span> · <span class="text-secondary">{{ $plan->formattedPrice('year') }}/year</span></div>
                        <p class="small text-secondary">{{ $plan->description }}</p>
                        <div class="small mb-2"><span class="text-secondary">Trial:</span> {{ $plan->trial_days }} days · <span class="text-secondary">Tenants:</span> {{ $plan->tenants_count }}</div>
                        <div class="small mb-2">
                            @foreach (\App\Modules\Saas\Models\Plan::LIMITS as $key => $label)
                                <div><span class="text-secondary">{{ $label }}:</span> {{ $plan->limit($key) === null ? 'Unlimited' : number_format((int) $plan->limit($key)) }}</div>
                            @endforeach
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            @foreach ($moduleNames as $slug => $name)
                                <span class="badge {{ in_array($slug, $plan->modules ?? [], true) ? 'badge-soft-success' : 'badge-soft-secondary text-decoration-line-through' }}">{{ $name }}</span>
                            @endforeach
                        </div>
                        <div class="small text-secondary mb-3">
                            Razorpay: {{ $plan->razorpay_plan_monthly ? '✓' : '—' }} · Stripe: {{ $plan->stripe_price_monthly ? '✓' : '—' }}
                        </div>
                        <div class="mt-auto d-flex gap-2">
                            @can('plans.edit')<a href="{{ route('admin.saas.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary flex-fill">Edit</a>@endcan
                            @can('plans.delete')
                                <x-admin.delete-button :action="route('admin.saas.plans.destroy', $plan)" :confirm="'Delete plan '.$plan->name.'?'" body="Only possible when no tenant is on it." button="Delete" />
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><x-admin.empty icon="bi-tags" message="No plans yet." /></div>
        @endforelse
    </div>
@endsection
