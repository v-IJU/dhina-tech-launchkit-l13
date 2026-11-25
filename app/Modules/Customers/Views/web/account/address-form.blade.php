@extends('customers::web.account.layout')

@section('title', $address->exists ? __('Edit address') : __('Add address'))

@section('account')
    @component('customers::web.account._card', ['title' => $address->exists ? __('Edit address') : __('Add address')])
        <form method="POST" action="{{ $address->exists ? route('customer.addresses.update', $address) : route('customer.addresses.store') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf
            @if ($address->exists) @method('PUT') @endif
            @include('customers::web._field', ['name' => 'label', 'label' => __('Label'), 'value' => $address->label, 'optional' => true, 'hint' => __('e.g. Home, Office')])
            @include('customers::web._field', ['name' => 'name', 'label' => __('Full name'), 'value' => $address->name, 'required' => true, 'autocomplete' => 'name'])
            @include('customers::web._field', ['name' => 'line1', 'label' => __('Address line 1'), 'value' => $address->line1, 'required' => true, 'autocomplete' => 'address-line1', 'class' => 'sm:col-span-2'])
            @include('customers::web._field', ['name' => 'line2', 'label' => __('Address line 2'), 'value' => $address->line2, 'optional' => true, 'autocomplete' => 'address-line2', 'class' => 'sm:col-span-2'])
            @include('customers::web._field', ['name' => 'city', 'label' => __('City'), 'value' => $address->city, 'required' => true, 'autocomplete' => 'address-level2'])
            @include('customers::web._field', ['name' => 'postal_code', 'label' => __('PIN / postal code'), 'value' => $address->postal_code, 'autocomplete' => 'postal-code'])
            @include('customers::web._field', ['name' => 'state', 'label' => __('State'), 'value' => $address->state, 'autocomplete' => 'address-level1'])
            @include('customers::web._field', ['name' => 'country', 'label' => __('Country'), 'value' => $address->country, 'required' => true, 'autocomplete' => 'country-name'])
            @include('customers::web._field', ['name' => 'phone', 'label' => __('Phone for delivery'), 'type' => 'tel', 'value' => $address->phone, 'optional' => true, 'autocomplete' => 'tel'])
            <label class="flex items-center gap-2 self-end pb-3 text-sm text-slate-700">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address->is_default)) @disabled($address->is_default) class="h-4 w-4 rounded border-slate-300 text-brand-600">
                {{ __('Use as my default address') }}
            </label>
            <div class="flex gap-3 sm:col-span-2">
                <button class="btn-brand">{{ __('Save address') }}</button>
                <a href="{{ route('customer.addresses.index') }}" class="btn-outline">{{ __('Cancel') }}</a>
            </div>
        </form>
    @endcomponent
@endsection
