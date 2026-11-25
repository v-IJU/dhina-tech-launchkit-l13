@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Onboard tenant" :breadcrumbs="['SaaS', 'Tenants' => route('admin.saas.tenants.index'), 'Onboard']">
        <a href="{{ route('admin.saas.tenants.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.saas.tenants.store') }}" novalidate>
        @csrf
        <div class="row g-4">
            <div class="col-xl-8 d-flex flex-column gap-4">
                <x-admin.card title="Company" subtitle="Creates a new database, runs every migration, sets up roles, menus and settings.">
                    <div class="row g-3">
                        <x-admin.form.input class="col-md-6" name="name" label="Company name" required placeholder="Acme Florists" />
                        <div class="col-md-6">
                            <label for="f_subdomain" class="form-label">Subdomain <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input id="f_subdomain" name="subdomain" value="{{ old('subdomain') }}" class="form-control @error('subdomain') is-invalid @enderror" placeholder="acme" required>
                                <span class="input-group-text">.{{ config('tenancy.central_domains.0') }}</span>
                                @error('subdomain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">Lowercase letters, numbers and dashes. Also the tenant ID (database <code>{{ config('tenancy.database.prefix') }}acme</code>). Their own domain can be added later.</div>
                        </div>
                        <x-admin.form.input class="col-md-6" name="gstin" label="Their GSTIN" placeholder="Optional, printed on invoices" />
                        <x-admin.form.input class="col-md-6" name="domain" label="Their own domain" placeholder="Optional, e.g. www.acmeflorists.com" hint="Point its DNS at this server. Can be added later too." />
                        <x-admin.form.textarea class="col-12" name="notes" label="Staff notes" rows="2" placeholder="Only visible in this console" />
                    </div>
                </x-admin.card>

                <x-admin.card title="Owner" subtitle="Gets the Super Admin role inside their own panel, and an email to choose a password.">
                    <div class="row g-3">
                        <x-admin.form.input class="col-md-6" name="owner_name" label="Full name" required />
                        <x-admin.form.input class="col-md-6" name="owner_email" type="email" label="Email" required hint="Also used for billing emails." />
                        <x-admin.form.input class="col-md-6" name="password" type="password" label="Password" autocomplete="new-password" hint="Leave empty: they get an email to choose one." />
                    </div>
                </x-admin.card>
            </div>

            <div class="col-xl-4 d-flex flex-column gap-4">
                <x-admin.card title="Plan">
                    <div class="d-flex flex-column gap-3">
                        <x-admin.form.select name="plan_id" label="Plan" :options="$plans->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->formattedPrice().'/month'])->all()" required />
                        <x-admin.form.input name="trial_days" type="number" label="Free trial (days)" placeholder="Plan default" hint="0 = no trial: they start as Active (record their payment afterwards)." />
                        <x-admin.form.checkbox name="with_demo" label="Add demo website content" hint="Sample pages, services and posts to start from." />
                    </div>
                </x-admin.card>
                <div class="card card-body flex-grow-0 d-grid gap-2">
                    <button class="btn btn-primary btn-lg fs-6" data-loading-text="Creating database…">Create tenant</button>
                    <small class="text-secondary text-center">Takes a few seconds.</small>
                </div>
            </div>
        </div>
    </form>
@endsection
