@extends('customers::web.auth.layout')

@section('title', __('Choose a new password'))
@section('heading', __('Choose a new password'))

@section('form')
    <form method="POST" action="{{ route('customer.password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @include('customers::web._field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'value' => $email, 'required' => true, 'autocomplete' => 'email'])
        @include('customers::web._field', ['name' => 'password', 'label' => __('New password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password'])
        @include('customers::web._field', ['name' => 'password_confirmation', 'label' => __('Confirm new password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password'])
        <button class="btn-brand w-full">{{ __('Save new password') }}</button>
    </form>
@endsection
