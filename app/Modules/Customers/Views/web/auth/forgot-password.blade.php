@extends('customers::web.auth.layout')

@section('title', __('Forgot password'))
@section('heading', __('Forgot your password?'))
@section('intro', __('Enter your email and we will send you a link to choose a new one.'))

@section('form')
    <form method="POST" action="{{ route('customer.password.email') }}" class="space-y-5">
        @csrf
        @include('customers::web._field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
        <button class="btn-brand w-full">{{ __('Send reset link') }}</button>
    </form>
@endsection

@section('below')
    <a href="{{ route('customer.login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Back to sign in') }}</a>
@endsection
