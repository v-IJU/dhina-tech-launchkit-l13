<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ setting('site_name', config('app.name')) }}</title>
    @vite(['resources/admin/admin.css', 'resources/admin/admin.js'])
    @unless (\App\Core\Support\BrandColor::isDefault())<style>{!! \App\Core\Support\BrandColor::adminCss() !!}</style>@endunless
</head>
<body>
<div class="auth-wrap">
    <section class="auth-side">
        <div class="d-flex align-items-center gap-3">
            <span class="brand-logo">
                @if ($logo = setting('logo'))<img src="{{ $logo }}" alt="">@else{{ initials(setting('site_name', config('app.name'))) }}@endif
            </span>
            <span class="text-white fw-bold fs-6">{{ setting('site_name', config('app.name')) }}</span>
        </div>
        <div class="mt-auto">
            <h1>Manage your website and team in one place.</h1>
            <p class="fs-6 mb-0" style="color: #a9b3c4; max-width: 460px">Publish content, control who can do what, and add new modules whenever the business needs them.</p>
        </div>
        <div class="d-flex flex-column gap-3">
            @foreach ([['bi-shield-check', 'Role-based access', 'Every menu and button follows the role’s permissions.'], ['bi-window', 'Website CMS', 'Pages, blog, sliders, services and enquiries.'], ['bi-box-seam', 'Modules on demand', 'One command creates a full CRUD module.']] as [$icon, $title, $text])
                <div class="d-flex gap-3">
                    <span class="auth-feature-icon"><i class="bi {{ $icon }}"></i></span>
                    <div><div class="text-white fw-semibold">{{ $title }}</div><small style="color: #a9b3c4">{{ $text }}</small></div>
                </div>
            @endforeach
        </div>
        <small style="color: #8a95a8">© {{ date('Y') }} {{ setting('site_name', config('app.name')) }}</small>
    </section>
    <main class="auth-main">
        <div class="auth-card">
            @if (session('status'))
                <div class="alert alert-success small" role="status">{{ session('status') }}</div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
