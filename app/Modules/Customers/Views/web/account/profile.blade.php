@extends('customers::web.account.layout')

@section('title', __('My account'))

@section('account')
    @component('customers::web.account._card', ['title' => __('Your details'), 'text' => __('Used for your orders, bookings and messages from us.')])
        <form method="POST" action="{{ route('customer.profile.update') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf @method('PUT')
            @include('customers::web._field', ['name' => 'name', 'label' => __('Full name'), 'value' => $customer->name, 'bag' => 'profile', 'required' => true, 'autocomplete' => 'name'])
            @include('customers::web._field', ['name' => 'phone', 'label' => __('Mobile number'), 'type' => 'tel', 'value' => $customer->phone, 'bag' => 'profile', 'optional' => true, 'autocomplete' => 'tel'])
            @include('customers::web._field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'value' => $customer->email, 'bag' => 'profile', 'required' => true, 'autocomplete' => 'email',
                'hint' => $customer->hasVerifiedEmail() ? __('Confirmed') : __('Not confirmed yet')])
            @include('customers::web._field', ['name' => 'current_password', 'label' => __('Current password'), 'type' => 'password', 'bag' => 'profile', 'autocomplete' => 'current-password',
                'hint' => __('Only needed when you change your email.')])
            @include('customers::web._field', ['name' => 'date_of_birth', 'label' => __('Date of birth'), 'type' => 'date', 'value' => $customer->date_of_birth?->format('Y-m-d'), 'bag' => 'profile', 'optional' => true])
            <div>
                <label for="f_gender" class="field-label">{{ __('Gender') }} <span class="font-normal text-slate-400">{{ __('(optional)') }}</span></label>
                <select id="f_gender" name="gender" class="field">
                    <option value="">{{ __('Prefer not to say') }}</option>
                    @foreach (\App\Modules\Customers\Models\Customer::GENDERS as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $customer->gender) === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                <input type="hidden" name="marketing_opt_in" value="0">
                <input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in', $customer->marketing_opt_in)) class="h-4 w-4 rounded border-slate-300 text-brand-600">
                {{ __('Email me offers and updates') }}
            </label>
            <div class="sm:col-span-2"><button class="btn-brand">{{ __('Save details') }}</button></div>
        </form>
    @endcomponent

    @component('customers::web.account._card', ['title' => __('Change password')])
        <form method="POST" action="{{ route('customer.password.change') }}" class="grid gap-5 sm:grid-cols-3">
            @csrf @method('PUT')
            @include('customers::web._field', ['name' => 'current_password', 'id' => 'pw_current', 'label' => __('Current password'), 'type' => 'password', 'bag' => 'password', 'required' => true, 'autocomplete' => 'current-password'])
            @include('customers::web._field', ['name' => 'password', 'id' => 'pw_new', 'label' => __('New password'), 'type' => 'password', 'bag' => 'password', 'required' => true, 'autocomplete' => 'new-password'])
            @include('customers::web._field', ['name' => 'password_confirmation', 'id' => 'pw_confirm', 'label' => __('Confirm new password'), 'type' => 'password', 'bag' => 'password', 'required' => true, 'autocomplete' => 'new-password'])
            <div class="sm:col-span-3"><button class="btn-outline">{{ __('Change password') }}</button></div>
        </form>
    @endcomponent

    @component('customers::web.account._card', ['title' => __('Delete account'), 'text' => __('Your account and saved addresses are removed. This cannot be undone from your side.'), 'danger' => true])
        <form method="POST" action="{{ route('customer.destroy') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-confirm="{{ __('Delete your account?') }}">
            @csrf @method('DELETE')
            @include('customers::web._field', ['name' => 'password', 'id' => 'del_password', 'label' => __('Type your password to confirm'), 'type' => 'password', 'bag' => 'deleteAccount', 'required' => true, 'autocomplete' => 'current-password', 'class' => 'sm:w-72'])
            <button class="inline-flex items-center justify-center rounded-lg bg-red-600 px-5 py-3 text-sm font-semibold text-white hover:bg-red-700">{{ __('Delete my account') }}</button>
        </form>
    @endcomponent
@endsection
