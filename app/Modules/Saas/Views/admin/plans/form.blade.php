@extends('layouts.admin')

@php $editing = $plan->exists; $money = fn ($v) => \App\Modules\Saas\Support\Money::toMajor((int) $v); @endphp

@section('content')
    <x-admin.page-header :title="$editing ? 'Edit plan: '.$plan->name : 'Add plan'" :breadcrumbs="['SaaS', 'Plans' => route('admin.saas.plans.index'), $editing ? 'Edit' : 'Add']">
        <a href="{{ route('admin.saas.plans.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ $editing ? route('admin.saas.plans.update', $plan) : route('admin.saas.plans.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-xl-8 d-flex flex-column gap-4">
                <x-admin.card title="Plan">
                    <div class="row g-3">
                        <x-admin.form.input class="col-md-6" name="name" label="Name" :value="$plan->name" required placeholder="Business" />
                        <x-admin.form.input class="col-md-6" name="slug" label="Code" :value="$plan->slug" placeholder="business (from the name)" />
                        <x-admin.form.input class="col-12" name="description" label="Short description" :value="$plan->description" placeholder="Shown on the pricing page" />
                        <x-admin.form.input class="col-md-4" name="price_monthly" type="number" label="Price per month" :value="$money($plan->price_monthly)" required hint="Before tax, e.g. 2499" />
                        <x-admin.form.input class="col-md-4" name="price_yearly" type="number" label="Price per year" :value="$money($plan->price_yearly)" required hint="e.g. 24990 (2 months free)" />
                        <x-admin.form.input class="col-md-2" name="currency" label="Currency" :value="$plan->currency" required />
                        <x-admin.form.input class="col-md-2" name="trial_days" type="number" label="Trial days" :value="$plan->trial_days" required />
                        <x-admin.form.textarea class="col-12" name="features" label="Pricing page bullet points" :value="implode(\"\n\", $plan->features ?? [])" rows="5" hint="One per line." />
                    </div>
                </x-admin.card>

                <x-admin.card title="Modules" subtitle="What tenants on this plan can use. Changes apply to them straight away.">
                    <div class="small text-secondary mb-2">Always included: @foreach ($core as $m)<span class="badge badge-soft-secondary">{{ $m->name }}</span> @endforeach</div>
                    <div class="row g-2">
                        @foreach ($modules as $slug => $module)
                            <div class="col-md-4">
                                <label class="d-flex align-items-start gap-2 border rounded-3 px-3 py-2 h-100" style="cursor: pointer">
                                    <input type="checkbox" class="form-check-input mt-1" name="modules[]" value="{{ $slug }}" @checked(in_array($slug, old('modules', $plan->modules ?? []), true))>
                                    <span><span class="fw-semibold d-block">{{ $module->name }}</span><small class="text-secondary">{{ \Illuminate\Support\Str::limit($module->description, 70) }}</small></span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>

                <x-admin.card title="Limits" subtitle="Leave empty for unlimited.">
                    <div class="row g-3">
                        @foreach (\App\Modules\Saas\Models\Plan::LIMITS as $key => $label)
                            <x-admin.form.input class="col-md-4" :name="'limits['.$key.']'" :id="'limit_'.$key" type="number" :label="$label" :value="($plan->limits ?? [])[$key] ?? ''" placeholder="Unlimited" />
                        @endforeach
                    </div>
                </x-admin.card>

                <x-admin.card title="Payment gateways" subtitle="Create the same prices in each dashboard and paste their ids. Include tax in the gateway price (₹2,499 + 18% = ₹2,948.82).">
                    <div class="row g-3">
                        <x-admin.form.input class="col-md-6" name="razorpay_plan_monthly" label="Razorpay plan id, monthly" :value="$plan->razorpay_plan_monthly" placeholder="plan_N8xAbc…" />
                        <x-admin.form.input class="col-md-6" name="razorpay_plan_yearly" label="Razorpay plan id, yearly" :value="$plan->razorpay_plan_yearly" placeholder="plan_N8xDef…" />
                        <x-admin.form.input class="col-md-6" name="stripe_price_monthly" label="Stripe price id, monthly" :value="$plan->stripe_price_monthly" placeholder="price_1P…" />
                        <x-admin.form.input class="col-md-6" name="stripe_price_yearly" label="Stripe price id, yearly" :value="$plan->stripe_price_yearly" placeholder="price_1Q…" />
                    </div>
                </x-admin.card>
            </div>
            <div class="col-xl-4 d-flex flex-column gap-4">
                <x-admin.card title="Visibility">
                    <div class="d-flex flex-column gap-3">
                        <x-admin.form.checkbox name="is_active" label="Can be chosen" hint="Off = existing tenants keep it, nobody new can pick it." :checked="$plan->is_active" />
                        <x-admin.form.checkbox name="is_public" label="Show on the pricing page" hint="Off = only you can assign it (custom deals)." :checked="$plan->is_public" />
                        <x-admin.form.checkbox name="is_featured" label="Highlight as recommended" :checked="$plan->is_featured" />
                        <x-admin.form.input name="sort_order" type="number" label="Order" :value="$plan->sort_order" />
                    </div>
                </x-admin.card>
                <div class="card card-body flex-grow-0 d-grid"><button class="btn btn-primary">{{ $editing ? 'Save plan' : 'Create plan' }}</button></div>
            </div>
        </div>
    </form>
@endsection
