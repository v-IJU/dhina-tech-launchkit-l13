@extends('customers::web.auth.layout')

@section('title', __('Create an account'))
@section('heading', __('Create your account'))
@section('intro', __('Save your details and addresses, and manage everything in one place.'))

@section('form')
    <form method="POST" action="{{ route('customer.register.store') }}" class="space-y-5">
        @csrf
        @include('customers::web._field', ['name' => 'name', 'label' => __('Full name'), 'required' => true, 'autocomplete' => 'name'])
        @include('customers::web._field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
        @include('customers::web._field', ['name' => 'phone', 'label' => __('Mobile number'), 'type' => 'tel', 'optional' => true, 'autocomplete' => 'tel'])
        @include('customers::web._field', ['name' => 'password', 'label' => __('Password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password',
            'hint' => __('At least :n characters with upper and lower case letters, a number and a symbol.', ['n' => setting('password_min_length', 8)])])
        @include('customers::web._field', ['name' => 'password_confirmation', 'label' => __('Confirm password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password'])
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

        <div class="space-y-3 text-sm text-slate-700">
            <label class="flex items-start gap-2">
                <input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in')) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600">
                {{ __('Email me offers and updates') }}
            </label>
            <label class="flex items-start gap-2">
                <input type="checkbox" name="terms" value="1" required @checked(old('terms')) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600">
                <span>{!! __('I agree to the :terms and :privacy', ['terms' => '<a href="'.e(url('/terms')).'" class="font-semibold text-brand-700 hover:underline">'.e(__('Terms')).'</a>', 'privacy' => '<a href="'.e(url('/privacy')).'" class="font-semibold text-brand-700 hover:underline">'.e(__('Privacy policy')).'</a>']) !!}</span>
            </label>
            @error('terms')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button class="btn-brand w-full">{{ __('Create account') }}</button>
    </form>
@endsection

@section('below')
    {{ __('Already have an account?') }} <a href="{{ route('customer.login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Sign in') }}</a>
@endsection
