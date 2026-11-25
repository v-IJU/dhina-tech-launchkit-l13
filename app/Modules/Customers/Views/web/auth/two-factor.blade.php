@extends('customers::web.auth.layout')

@section('title', __('Two-factor login'))
@section('heading', __('Enter your security code'))
@section('intro', __('Open your authenticator app and enter the 6-digit code. Lost your phone? Enter one of your recovery codes instead.'))

@section('form')
    <form method="POST" action="{{ route('customer.two-factor.store') }}" class="space-y-5">
        @csrf
        @include('customers::web._field', ['name' => 'code', 'label' => __('Code'), 'required' => true, 'autocomplete' => 'one-time-code', 'inputmode' => 'text'])
        <button class="btn-brand w-full">{{ __('Verify and sign in') }}</button>
    </form>
@endsection

@section('below')
    <a href="{{ route('customer.login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Back to sign in') }}</a>
@endsection
