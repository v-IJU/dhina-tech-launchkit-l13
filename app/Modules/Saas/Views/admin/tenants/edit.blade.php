@extends('layouts.admin')

@section('content')
    <x-admin.page-header :title="'Edit '.$tenant->name" :breadcrumbs="['SaaS', 'Tenants' => route('admin.saas.tenants.index'), $tenant->name => route('admin.saas.tenants.show', $tenant), 'Edit']">
        <a href="{{ route('admin.saas.tenants.show', $tenant) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.saas.tenants.update', $tenant) }}" novalidate>
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-xl-8 d-flex flex-column gap-4">
                <x-admin.card title="Details">
                    <div class="row g-3">
                        <x-admin.form.input class="col-md-6" name="name" label="Company name" :value="$tenant->name" required />
                        <x-admin.form.input class="col-md-6" name="email" type="email" label="Billing email" :value="$tenant->email" required />
                        <x-admin.form.input class="col-md-6" name="gstin" label="GSTIN" :value="$tenant->gstin" />
                        <x-admin.form.textarea class="col-12" name="billing_address" label="Billing address" :value="$tenant->billing_address" rows="2" />
                        <x-admin.form.textarea class="col-12" name="notes" label="Staff notes" :value="$tenant->notes" rows="3" />
                    </div>
                </x-admin.card>

                <x-admin.card title="Extra modules" subtitle="On top of the plan, for this tenant only (e.g. a Blog given as a goodwill gesture).">
                    <div class="row g-2">
                        @foreach ($modules as $slug => $module)
                            @php $inPlan = in_array($slug, $tenant->plan?->modules ?? [], true); @endphp
                            <div class="col-md-4">
                                <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2">
                                    <input type="checkbox" class="form-check-input m-0" name="extra_modules[]" value="{{ $slug }}" @checked(in_array($slug, (array) ($tenant->extra_modules ?? []), true)) @disabled($inPlan)>
                                    {{ $module->name }} @if ($inPlan)<span class="badge badge-soft-success ms-auto">In plan</span>@endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>

                <x-admin.card title="Limit overrides" subtitle="Leave empty to use the plan's limits.">
                    <div class="row g-3">
                        @foreach (\App\Modules\Saas\Models\Plan::LIMITS as $key => $label)
                            <x-admin.form.input class="col-md-4" :name="'limit_overrides['.$key.']'" :id="'lim_'.$key" type="number" :label="$label" :value="((array) ($tenant->limit_overrides ?? []))[$key] ?? ''" :placeholder="'Plan: '.($tenant->plan?->limit($key) ?? '∞')" />
                        @endforeach
                    </div>
                </x-admin.card>
            </div>
            <div class="col-xl-4 d-flex flex-column gap-4">
                <x-admin.card title="Plan and status" subtitle="Changing these by hand does not charge anyone.">
                    <div class="d-flex flex-column gap-3">
                        <x-admin.form.select name="plan_id" label="Plan" :options="$plans->pluck('name', 'id')->all()" :value="$tenant->plan_id" placeholder="No plan" />
                        <x-admin.form.select name="status" label="Status" :options="\App\Modules\Saas\Models\Tenant::STATUSES" :value="$tenant->status" />
                        <x-admin.form.input name="trial_ends_at" type="date" label="Trial ends" :value="$tenant->trial_ends_at?->format('Y-m-d')" />
                    </div>
                </x-admin.card>
                <div class="card card-body flex-grow-0 d-grid"><button class="btn btn-primary">Save</button></div>
            </div>
        </div>
    </form>
@endsection
