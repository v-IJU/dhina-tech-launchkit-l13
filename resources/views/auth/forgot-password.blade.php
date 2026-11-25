@extends('layouts.auth')
@section('title', 'Reset password')

@section('content')
    <h2 class="h4 fw-bold mb-1">Reset your password</h2>
    <p class="text-secondary mb-4">We’ll email you a secure link that expires in 60 minutes.</p>

    <form method="POST" action="{{ route('password.email') }}" class="d-flex flex-column gap-3">
        @csrf
        <x-admin.form.input name="email" type="email" label="Email address" required autofocus />
        <button class="btn btn-primary btn-lg fs-6">Send reset link</button>
    </form>
    <a href="{{ route('login') }}" class="d-inline-block mt-3 small fw-semibold"><i class="bi bi-arrow-left"></i> Back to sign in</a>
@endsection
