@php
    $siteName = setting('site_name', config('app.name'));
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle ? $pageTitle.' | '.$siteName : (setting('meta_title') ?: $siteName.' — '.setting('site_tagline'));
    $description = trim($__env->yieldContent('meta_description')) ?: setting('meta_description', setting('site_tagline'));
    $ogImage = trim($__env->yieldContent('og_image')) ?: setting('og_image');
    $socials = array_filter([
        'Facebook' => setting('social_facebook'), 'Instagram' => setting('social_instagram'), 'LinkedIn' => setting('social_linkedin'),
        'X' => setting('social_x'), 'YouTube' => setting('social_youtube'),
    ]);
@endphp
<!DOCTYPE html>
@php $locales = \App\Core\Localization\Locales::class; @endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $locales::dir() }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @unless (setting('allow_indexing', true))
        <meta name="robots" content="noindex, nofollow">
    @endunless
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $pageTitle ?: $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
    <meta name="twitter:card" content="summary_large_image">
    @if ($favicon = setting('favicon'))<link rel="icon" href="{{ $favicon }}">@endif
    @vite(['resources/web/web.css', 'resources/web/web.js'])
    @unless (\App\Core\Support\BrandColor::isDefault())<style>{!! \App\Core\Support\BrandColor::websiteCss() !!}</style>@endunless
    @if ($ga = setting('google_analytics_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}" nonce="{{ Vite::cspNonce() }}"></script>
        <script nonce="{{ Vite::cspNonce() }}">window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', @json($ga));</script>
    @endif
    <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode(array_filter([
        '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $siteName, 'url' => url('/'),
        'logo' => setting('logo'), 'email' => setting('contact_email'), 'telephone' => setting('contact_phone'), 'sameAs' => array_values($socials),
    ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @if ($locales::isMultilingual())
        {{-- Same page in every website language (?lang=xx), for search engines --}}
        @foreach ($locales::enabled() as $code)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ $locales::switchUrl($code) }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ $locales::switchUrl($locales::default()) }}">
    @endif
    @stack('head')
</head>
<body class="bg-white font-sans text-slate-700 antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">{{ __('Skip to content') }}</a>

@php $customerAccounts = module_enabled('customers') && setting('customer_accounts', true); @endphp
@if ($customerAccounts && session('customer_impersonated_by') && auth('customer')->check())
    {{-- Staff used "Sign in as customer" in the admin panel --}}
    <div class="bg-amber-500 text-amber-950">
        <div class="container-site flex flex-wrap items-center justify-between gap-2 py-2 text-sm font-semibold">
            <span>You are viewing the website as {{ auth('customer')->user()->name }}.</span>
            <form method="POST" action="{{ route('customer.impersonate.stop') }}">@csrf<button class="rounded-md bg-amber-950 px-3 py-1 text-white hover:bg-black">Back to admin panel</button></form>
        </div>
    </div>
@endif

<header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur">
    <div class="container-site flex h-18 items-center justify-between gap-6 py-4">
        <a href="{{ url('/') }}" class="flex items-center gap-3">
            @if ($logo = setting('logo'))
                <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-9 w-auto">
            @else
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 font-bold text-white">{{ initials($siteName) }}</span>
                <span class="text-lg font-bold text-ink">{{ $siteName }}</span>
            @endif
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
            @foreach ($headerMenu as $item)
                @php $active = rtrim(parse_url($item['url'], PHP_URL_PATH) ?? '/', '/') === rtrim('/'.request()->path(), '/') || ($item['url'] !== url('/') && str_starts_with(url()->current(), rtrim($item['url'], '/').'/')); @endphp
                <div class="group relative">
                    <a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                       @class(['rounded-lg px-3 py-2 text-sm font-semibold transition', 'text-brand-700 bg-brand-50' => $active, 'text-slate-700 hover:text-ink hover:bg-slate-50' => ! $active])>{{ $item['label'] }}</a>
                    @if ($item['children'])
                        <div class="invisible absolute left-0 top-full z-50 min-w-52 rounded-xl border border-slate-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                            @foreach ($item['children'] as $child)
                                <a href="{{ $child['url'] }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ $child['label'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </nav>

        <div class="flex items-center gap-3">
            @if ($locales::isMultilingual())
                {{-- Language switcher: works without JavaScript (<details>) --}}
                <details class="relative" data-lang-switcher>
                    <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="{{ __('Language') }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></svg>
                        {{ strtoupper(app()->getLocale()) }}
                    </summary>
                    <div class="absolute end-0 top-full z-50 mt-2 min-w-44 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                        @foreach ($locales::enabled() as $code)
                            <a href="{{ $locales::switchUrl($code) }}" hreflang="{{ $code }}" lang="{{ $code }}"
                               @class(['block rounded-lg px-3 py-2 text-sm hover:bg-slate-50', 'font-semibold text-brand-700' => $code === app()->getLocale(), 'text-slate-700' => $code !== app()->getLocale()])>
                                {{ $locales::name($code, true) }}
                            </a>
                        @endforeach
                    </div>
                </details>
            @endif
            @if ($customerAccounts)
                {{-- Customer accounts (Customers module) --}}
                <a href="{{ auth('customer')->check() ? route('customer.account') : route('customer.login') }}" class="hidden items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-ink md:inline-flex">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm-9 9a9 9 0 0 1 18 0"/></svg>
                    {{ auth('customer')->check() ? __('My account') : __('Sign in') }}
                </a>
            @endif
            <a href="{{ url('/contact') }}" class="btn-brand hidden sm:inline-flex">{{ __('Get in touch') }}</a>
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 lg:hidden" data-menu-toggle aria-controls="mobileMenu" aria-expanded="false" aria-label="{{ __('Open menu') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
        </div>
    </div>
    <nav id="mobileMenu" class="hidden border-t border-slate-200 bg-white lg:hidden" aria-label="Mobile">
        <div class="container-site flex flex-col py-3">
            @foreach ($headerMenu as $item)
                <a href="{{ $item['url'] }}" class="rounded-lg px-3 py-2.5 font-semibold text-slate-800 hover:bg-slate-50">{{ $item['label'] }}</a>
                @foreach ($item['children'] as $child)
                    <a href="{{ $child['url'] }}" class="rounded-lg px-6 py-2 text-sm text-slate-600 hover:bg-slate-50">{{ $child['label'] }}</a>
                @endforeach
            @endforeach
            @if ($customerAccounts)
                <a href="{{ auth('customer')->check() ? route('customer.account') : route('customer.login') }}" class="rounded-lg px-3 py-2.5 font-semibold text-slate-800 hover:bg-slate-50">{{ auth('customer')->check() ? __('My account') : __('Sign in') }}</a>
            @endif
            <a href="{{ url('/contact') }}" class="btn-brand mt-2">{{ __('Get in touch') }}</a>
        </div>
    </nav>
</header>

@if (session('success'))
    <div class="bg-emerald-50 text-emerald-800" role="status"><div class="container-site py-3 text-sm font-medium">{{ session('success') }}</div></div>
@endif

<main id="main">
    @yield('content')
</main>

<footer class="bg-ink text-slate-400">
    <div class="container-site grid gap-10 py-16 md:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-1">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 font-bold text-white">{{ initials($siteName) }}</span>
                <span class="text-lg font-bold text-white">{{ $siteName }}</span>
            </div>
            <p class="mt-4 text-sm leading-relaxed">{{ setting('footer_text') }}</p>
            @if ($socials)
                <div class="mt-5 flex flex-wrap gap-3 text-sm">
                    @foreach ($socials as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-700 px-3 py-1.5 hover:border-slate-500 hover:text-white">{{ $network }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <h2 class="text-sm font-semibold tracking-wide text-white uppercase">{{ __('Company') }}</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach ($footerMenu as $item)
                    <li><a href="{{ $item['url'] }}" class="hover:text-white">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="text-sm font-semibold tracking-wide text-white uppercase">{{ __('Contact') }}</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                @if ($email = setting('contact_email'))<li><a href="mailto:{{ $email }}" class="hover:text-white">{{ $email }}</a></li>@endif
                @if ($phone = setting('contact_phone'))<li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-white">{{ $phone }}</a></li>@endif
                @if ($hours = setting('business_hours'))<li>{{ $hours }}</li>@endif
                @if ($address = setting('contact_address'))<li class="whitespace-pre-line">{{ $address }}</li>@endif
            </ul>
        </div>
        @if (module_enabled('inbox'))
            <div>
                <h2 class="text-sm font-semibold tracking-wide text-white uppercase">{{ __('Newsletter') }}</h2>
                <p class="mt-4 text-sm">{{ __('Occasional updates. No spam, unsubscribe any time.') }}</p>
                <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-4 flex flex-col gap-2 sm:flex-row lg:flex-col xl:flex-row">
                    @csrf
                    <label for="newsletterEmail" class="sr-only">{{ __('Email address') }}</label>
                    <input id="newsletterEmail" type="email" name="email" required placeholder="you@company.com" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-900 px-3.5 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:outline-none">
                    <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                    <button class="btn-brand">{{ __('Subscribe') }}</button>
                </form>
                @error('email', 'newsletter')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
        @endif
    </div>
    <div class="border-t border-slate-800">
        <div class="container-site flex flex-col justify-between gap-2 py-6 text-sm sm:flex-row">
            <span>© {{ date('Y') }} {{ $siteName }}. {{ __('All rights reserved.') }}</span>
            <span>{{ setting('site_tagline') }}</span>
        </div>
    </div>
</footer>

@if ($wa = setting('whatsapp_number'))
    <a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}" target="_blank" rel="noopener" class="fixed right-5 bottom-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg transition hover:bg-emerald-600" aria-label="{{ __('Chat on WhatsApp') }}">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2.1 1-2.4c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .6l-.4.6-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.7-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/></svg>
    </a>
@endif
</body>
</html>
