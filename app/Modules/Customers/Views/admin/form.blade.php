@extends('layouts.admin')

@php $editing = $customer->exists; @endphp

@section('content')
    <x-admin.page-header :title="$editing ? 'Edit customer' : 'Add customer'" :breadcrumbs="['Customers', 'All customers' => route('admin.customers.index'), $editing ? 'Edit' : 'Add']">
        <a href="{{ $editing ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <span>Please fix {{ $errors->count() }} highlighted field(s) before saving.</span>
        </div>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.customers.update', $customer) : route('admin.customers.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-xl-8 d-flex flex-column gap-4">
                <x-admin.card title="Customer details" subtitle="The customer can change these themselves under My account.">
                    <div class="row g-3">
                        <x-admin.form.input class="col-md-6" name="name" label="Full name" :value="$customer->name" required />
                        <x-admin.form.input class="col-md-6" name="email" type="email" label="Email address" :value="$customer->email" required placeholder="name@example.com" hint="They sign in with this email." />
                        <x-admin.form.input class="col-md-6" name="phone" label="Mobile number" :value="$customer->phone" placeholder="9876543210" />
                        <x-admin.form.input class="col-md-6" name="date_of_birth" type="date" label="Date of birth" :value="$customer->date_of_birth?->format('Y-m-d')" />
                        <x-admin.form.select class="col-md-6" name="gender" label="Gender" :options="\App\Modules\Customers\Models\Customer::GENDERS" :value="$customer->gender" placeholder="Prefer not to say" />
                        <x-admin.form.select class="col-md-6" name="locale" label="Language" :options="collect(config('starter.locales'))->map(fn ($l) => $l['name'])->all()" :value="$customer->locale" placeholder="Website default" hint="Language for emails." />
                    </div>
                </x-admin.card>

                <x-admin.card title="Password" :subtitle="$editing ? 'Leave empty to keep the current password. Setting one signs them out of the app.' : 'Optional. Leave empty and tick “Send set-password email” so they choose their own.'">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="f_password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="f_password" autocomplete="new-password" @class(['form-control', 'is-invalid' => $errors->has('password')])>
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#f_password" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="button" class="btn btn-link btn-sm px-0" data-generate-password="#f_password, #f_password_confirmation">Generate strong password</button>
                        </div>
                        <div class="col-md-6">
                            <label for="f_password_confirmation" class="form-label">Confirm password</label>
                            <input type="password" name="password_confirmation" id="f_password_confirmation" autocomplete="new-password" class="form-control">
                        </div>
                    </div>
                </x-admin.card>
            </div>

            <div class="col-xl-4 d-flex flex-column gap-4">
                <x-admin.card title="Account">
                    <div class="d-flex flex-column gap-3">
                        <fieldset>
                            <legend class="form-label">Status</legend>
                            @foreach (['active' => ['Active', 'Can sign in on the website and app'], 'blocked' => ['Blocked', 'Cannot sign in. Signed out everywhere.']] as $value => [$label, $hint])
                                <label class="d-flex gap-2 align-items-start border rounded-3 p-2 px-3 mb-2" style="cursor: pointer">
                                    <input class="form-check-input mt-1" type="radio" name="status" value="{{ $value }}" @checked(old('status', $customer->status) === $value)>
                                    <span><span class="fw-semibold d-block">{{ $label }}</span><small class="text-secondary">{{ $hint }}</small></span>
                                </label>
                            @endforeach
                            @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
                        </fieldset>
                        <x-admin.form.checkbox name="email_verified" label="Email confirmed" hint="Skip the confirmation email." :checked="$editing ? (bool) $customer->email_verified_at : true" />
                        <x-admin.form.checkbox name="marketing_opt_in" label="Agreed to marketing emails" :checked="$customer->marketing_opt_in" />
                        @unless ($editing)
                            <x-admin.form.checkbox name="send_invite" label="Send set-password email" hint="A link to choose their own password (sent from the queue)." :checked="true" />
                        @endunless
                        <x-admin.form.textarea name="notes" label="Staff notes" :value="$customer->notes" rows="4" placeholder="Only visible to staff" />
                    </div>
                </x-admin.card>

                <div class="card card-body flex-grow-0 d-grid gap-2">
                    <button class="btn btn-primary btn-lg fs-6">{{ $editing ? 'Update customer' : 'Create customer' }}</button>
                    <a href="{{ $editing ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
@endsection
