@extends('customers::web.auth.layout')

@section('title', __('Confirm your email'))
@section('heading', __('Check your inbox'))
@section('intro', __('We sent a confirmation link to :email. Click it to start using your account.', ['email' => auth('customer')->user()->email]))

@section('form')
    <div class="flex flex-col gap-3 sm:flex-row">
        <form method="POST" action="{{ route('customer.verification.send') }}" class="flex-1">
            @csrf
            <button class="btn-brand w-full">{{ __('Send the link again') }}</button>
        </form>
        <form method="POST" action="{{ route('customer.logout') }}">
            @csrf
            <button class="btn-outline w-full">{{ __('Sign out') }}</button>
        </form>
    </div>
    <p class="mt-5 text-xs text-slate-500">{{ __('Wrong email address? Sign out and create the account again, or contact us.') }}</p>
@endsection
