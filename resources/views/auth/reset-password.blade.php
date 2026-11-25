@extends('layouts.auth')
@section('title', 'Choose a new password')

@section('content')
    <h2 class="h4 fw-bold mb-1">Choose a new password</h2>
    <p class="text-secondary mb-4">Use at least {{ setting('password_min_length', 8) }} characters with upper and lower case, a number and a symbol.</p>

    <form method="POST" action="{{ route('password.update') }}" class="d-flex flex-column gap-3">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-admin.form.input name="email" type="email" label="Email address" :value="$request->email" required />
        <x-admin.form.input name="password" type="password" label="New password" required autocomplete="new-password" />
        <x-admin.form.input name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" />
        <button class="btn btn-primary btn-lg fs-6">Reset password</button>
    </form>
@endsection
