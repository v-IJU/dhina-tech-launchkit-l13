@extends('layouts.admin')

@php $editing = $address->exists; @endphp

@section('content')
    <x-admin.page-header :title="$editing ? 'Edit address' : 'Add address'" :breadcrumbs="['Customers', $customer->name => route('admin.customers.show', $customer), $editing ? 'Edit address' : 'Add address']">
        <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ $editing ? route('admin.customers.addresses.update', [$customer, $address]) : route('admin.customers.addresses.store', $customer) }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-admin.card :title="'Address for '.$customer->name" style="max-width: 820px">
            <div class="row g-3">
                <x-admin.form.input class="col-md-6" name="label" label="Label" :value="$address->label" placeholder="Home, Office…" />
                <x-admin.form.input class="col-md-6" name="name" label="Full name" :value="$address->name" required />
                <x-admin.form.input class="col-12" name="line1" label="Address line 1" :value="$address->line1" required />
                <x-admin.form.input class="col-12" name="line2" label="Address line 2" :value="$address->line2" />
                <x-admin.form.input class="col-md-6" name="city" label="City" :value="$address->city" required />
                <x-admin.form.input class="col-md-6" name="postal_code" label="PIN / postal code" :value="$address->postal_code" />
                <x-admin.form.input class="col-md-6" name="state" label="State" :value="$address->state" />
                <x-admin.form.input class="col-md-6" name="country" label="Country" :value="$address->country" required />
                <x-admin.form.input class="col-md-6" name="phone" label="Phone for delivery" :value="$address->phone" />
                <div class="col-md-6 d-flex align-items-end">
                    <x-admin.form.checkbox name="is_default" label="Default address" :checked="$address->is_default" />
                </div>
            </div>
            <x-slot:footer>
                <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-link text-secondary">Cancel</a>
                <button class="btn btn-primary">Save address</button>
            </x-slot:footer>
        </x-admin.card>
    </form>
@endsection
