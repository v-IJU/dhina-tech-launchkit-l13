@extends('layouts.auth')
@section('title', 'Sign in')

@section('content')
    <h2 class="h4 fw-bold mb-1">Sign in to admin</h2>
    <p class="text-secondary mb-4">Welcome back. Enter your details to continue.</p>

    <form method="POST" action="{{ route('login') }}" class="d-flex flex-column gap-3" novalidate>
        @csrf
        <x-admin.form.input name="email" type="email" label="Email address" required autofocus autocomplete="username" />

        <div>
            <div class="d-flex justify-content-between align-items-baseline">
                <label for="password" class="form-label">Password</label>
                <a href="{{ route('password.request') }}" class="small fw-semibold">Forgot password?</a>
            </div>
            <div class="input-group">
                <input type="password" name="password" id="password" required autocomplete="current-password"
                       @class(['form-control', 'is-invalid' => $errors->has('password')])>
                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
            <label class="form-check-label" for="remember">Keep me signed in</label>
        </div>

        <button class="btn btn-primary btn-lg fs-6">Sign in</button>
    </form>

    @if (setting('passkeys_enabled', true))
        <div data-passkey-supported data-passkey-root hidden class="mt-3">
            <div class="d-flex align-items-center gap-2 my-3 text-secondary small"><hr class="flex-fill m-0"> or <hr class="flex-fill m-0"></div>
            <button type="button" class="btn btn-outline-secondary btn-lg fs-6 w-100" data-passkey-login
                    data-options-url="{{ route('passkey.login-options') }}" data-login-url="{{ route('passkey.login') }}" data-remember="#remember">
                <i class="bi bi-fingerprint me-1"></i> Sign in with a passkey
            </button>
            <div class="text-danger small mt-2" data-passkey-error hidden role="alert"></div>
        </div>
    @endif
@endsection
