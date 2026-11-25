@extends('customers::web.auth.layout')

@section('title', __('Sign in'))
@section('heading', __('Sign in to your account'))

@section('form')
    <form method="POST" action="{{ route('customer.login.store') }}" class="space-y-5">
        @csrf
        @include('customers::web._field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="f_password" class="text-sm font-semibold text-slate-800">{{ __('Password') }}</label>
                <a href="{{ route('customer.password.request') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Forgot password?') }}</a>
            </div>
            <input id="f_password" name="password" type="password" required autocomplete="current-password" class="field">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600"> {{ __('Keep me signed in') }}
        </label>
        <button class="btn-brand w-full">{{ __('Sign in') }}</button>
    </form>
@endsection

@if (\App\Modules\Customers\Support\CustomerAccounts::registrationOpen())
    @section('below')
        {{ __('New here?') }} <a href="{{ route('customer.register') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Create an account') }}</a>
    @endsection
@endif
