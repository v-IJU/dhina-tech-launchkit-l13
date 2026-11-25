<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title')</title>
    @vite(['resources/admin/admin.css'])
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh">
<main class="text-center p-4" style="max-width: 520px">
    <div class="fw-bold text-primary" style="font-size: 5rem; line-height: 1">@yield('code')</div>
    <h1 class="h3 fw-bold mt-3">@yield('title')</h1>
    <p class="text-secondary">@yield('message')</p>
    <div class="d-flex gap-2 justify-content-center mt-4">
        <a href="{{ url('/') }}" class="btn btn-outline-secondary">Go to website</a>
        @auth
            @if (Route::has('admin.dashboard'))
                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Back to dashboard</a>
            @endif
        @endauth
    </div>
</main>
</body>
</html>
