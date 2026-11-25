@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Choose a plan" :breadcrumbs="['System', 'Billing' => route('admin.billing.index'), 'Plans']">
        <a href="{{ route('admin.billing.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    @if (! $gateways)
        <div class="alert alert-warning">Online payment is not set up yet. Please contact us to pay by bank transfer.</div>
    @endif

    <form method="POST" action="{{ route('admin.billing.checkout') }}">
        @csrf
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <div class="btn-group" role="group" aria-label="Billing period">
                <input type="radio" class="btn-check" name="interval" id="i_month" value="month" checked>
                <label class="btn btn-outline-primary" for="i_month">Monthly</label>
                <input type="radio" class="btn-check" name="interval" id="i_year" value="year">
                <label class="btn btn-outline-primary" for="i_year">Yearly <span class="badge text-bg-success ms-1">2 months free</span></label>
            </div>
            @if (count($gateways) > 1)
                <div class="btn-group ms-md-auto" role="group" aria-label="Pay with">
                    @foreach ($gateways as $key => $label)
                        <input type="radio" class="btn-check" name="gateway" id="g_{{ $key }}" value="{{ $key }}" @checked($loop->first)>
                        <label class="btn btn-outline-secondary" for="g_{{ $key }}">{{ $label }}</label>
                    @endforeach
                </div>
            @elseif ($gateways)
                <input type="hidden" name="gateway" value="{{ array_key_first($gateways) }}">
                <span class="ms-md-auto small text-secondary">Secure payment by {{ reset($gateways) }}</span>
            @endif
        </div>
        @error('gateway')<div class="alert alert-danger">{{ $message }}</div>@enderror

        <div class="row g-4">
            @foreach ($plans as $plan)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 {{ $plan->is_featured ? 'border-primary' : '' }}">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center">
                                <h2 class="h5 mb-0 flex-fill">{{ $plan->name }}</h2>
                                @if ($tenant->plan_id === $plan->id)<span class="badge badge-soft-success">Current</span>@endif
                            </div>
                            <p class="small text-secondary mt-2">{{ $plan->description }}</p>
                            <div class="mb-3"><span class="fs-3 fw-bold">{{ $plan->formattedPrice() }}</span><span class="text-secondary">/month</span><div class="small text-secondary">or {{ $plan->formattedPrice('year') }}/year · + tax</div></div>
                            <ul class="small ps-3 flex-fill">
                                @foreach ($plan->features ?? [] as $feature)<li>{{ $feature }}</li>@endforeach
                            </ul>
                            <button name="plan_id" value="{{ $plan->id }}" class="btn {{ $plan->is_featured ? 'btn-primary' : 'btn-outline-primary' }}" @disabled(! $gateways)>Pay and switch to {{ $plan->name }}</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </form>
@endsection
