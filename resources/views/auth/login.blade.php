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
@endsection
