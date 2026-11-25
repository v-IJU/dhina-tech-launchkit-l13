@extends('layouts.auth')
@section('title', 'Two-factor check')

@section('content')
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary mb-3" style="width: 52px; height: 52px">
        <i class="bi bi-shield-lock fs-4"></i>
    </span>
    <h2 class="h4 fw-bold mb-1">Two-factor check</h2>

    @php $useRecovery = $errors->has('recovery_code') || request('recovery'); @endphp

    @if ($useRecovery)
        <p class="text-secondary mb-4">Enter one of your emergency recovery codes.</p>
        <form method="POST" action="{{ route('two-factor.login') }}" class="d-flex flex-column gap-3">
            @csrf
            <x-admin.form.input name="recovery_code" label="Recovery code" autocomplete="one-time-code" autofocus />
            <button class="btn btn-primary btn-lg fs-6">Verify and continue</button>
        </form>
        <a href="{{ route('two-factor.login') }}" class="d-inline-block mt-3 small fw-semibold">Use an authentication code instead</a>
    @else
        <p class="text-secondary mb-4">Enter the 6-digit code from your authenticator app.</p>
        <form method="POST" action="{{ route('two-factor.login') }}" class="d-flex flex-column gap-3">
            @csrf
            <div>
                <label for="code" class="form-label">Authentication code</label>
                <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                       @class(['form-control form-control-lg text-center fs-3', 'is-invalid' => $errors->has('code')]) style="letter-spacing: .5em">
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button class="btn btn-primary btn-lg fs-6">Verify and continue</button>
        </form>
        <a href="{{ route('two-factor.login', ['recovery' => 1]) }}" class="d-inline-block mt-3 small fw-semibold">Use a recovery code</a>
    @endif
@endsection
