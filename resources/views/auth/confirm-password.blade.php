@extends('layouts.auth')
@section('title', 'Confirm password')

@section('content')
    <h2 class="h4 fw-bold mb-1">Confirm your password</h2>
    <p class="text-secondary mb-4">This is a protected area. Please confirm your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="d-flex flex-column gap-3">
        @csrf
        <x-admin.form.input name="password" type="password" label="Password" required autofocus autocomplete="current-password" />
        <button class="btn btn-primary btn-lg fs-6">Confirm</button>
    </form>
@endsection
