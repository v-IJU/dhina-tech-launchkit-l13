<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="user-channel" content="{{ auth()->user()?->receivesBroadcastNotificationsOn() }}">
    @if (config('broadcasting.default') === 'reverb')
        <meta name="broadcast-auth" content="{{ url('/broadcasting/auth') }}">
        {{-- Read at runtime (not baked in by Vite), so one production image works on any domain. --}}
        <meta name="reverb" content="{{ json_encode(['key' => config('reverb.apps.apps.0.key'), 'host' => config('reverb.apps.apps.0.options.host'), 'port' => (int) config('reverb.apps.apps.0.options.port'), 'scheme' => config('reverb.apps.apps.0.options.scheme')]) }}">
    @endif
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') · {{ setting('site_name', config('app.name')) }} Admin</title>
    @if ($favicon = setting('favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <script nonce="{{ Vite::cspNonce() }}">
        try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'light'); } catch (e) {}
    </script>
    @vite(['resources/admin/admin.css', 'resources/admin/admin.js'])
    @unless (\App\Core\Support\BrandColor::isDefault())<style>{!! \App\Core\Support\BrandColor::adminCss() !!}</style>@endunless
    @stack('head')
</head>
<body>
@if (session('impersonator_id'))
    <div class="impersonation-bar">
        <span><i class="bi bi-person-badge me-1"></i> You are signed in as <strong>{{ auth()->user()->name }}</strong>.</span>
        <form method="POST" action="{{ route('admin.users.impersonate.stop') }}">
            @csrf
            <button class="btn btn-sm btn-light">Return to my account</button>
        </form>
    </div>
@endif

<div class="app">
    <aside class="app-sidebar" aria-label="Admin navigation">
        <a href="{{ route('admin.dashboard') }}" class="brand">
            <span class="brand-logo">
                @if ($logo = setting('logo'))
                    <img src="{{ $logo }}" alt="">
                @else
                    {{ initials(setting('site_name', config('app.name'))) }}
                @endif
            </span>
            <span class="brand-text d-flex flex-column lh-sm">
                <span class="fw-bold">{{ setting('site_name', config('app.name')) }}</span>
                <small style="color: var(--app-sidebar-muted)">Admin panel</small>
            </span>
        </a>

        <nav class="sidebar-nav">
            @foreach ($sidebar as $group)
                <div data-group="{{ $group['title'] }}">
                    <div class="nav-group-title">{{ $group['title'] }}</div>
                    @foreach ($group['items'] as $item)
                        @if ($item['children'])
                            @php
                                $activeChild = collect($item['children'])->first(fn ($c) => request()->routeIs($c['route']))
                                    ?? collect($item['children'])->first(fn ($c) => request()->routeIs($c['active']));
                                $open = (bool) $activeChild;
                                $collapseId = 'nav-'.\Illuminate\Support\Str::slug($item['key']);
                            @endphp
                            <button class="nav-link-app {{ $open ? 'parent-active' : '' }}" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#{{ $collapseId }}" aria-expanded="{{ $open ? 'true' : 'false' }}" title="{{ $item['label'] }}">
                                <i class="bi {{ $item['icon'] }}"></i>
                                <span class="label">{{ $item['label'] }}</span>
                                <i class="bi bi-chevron-right caret"></i>
                            </button>
                            <div class="collapse nav-children {{ $open ? 'show' : '' }}" id="{{ $collapseId }}">
                                @foreach ($item['children'] as $child)
                                    <a href="{{ route($child['route']) }}" class="nav-child {{ $activeChild === $child ? 'active' : '' }}">{{ $child['label'] }}</a>
                                @endforeach
                            </div>
                        @else
                            @php $isActive = $item['active'] && request()->routeIs($item['active']); @endphp
                            <a href="{{ $item['route'] ? route($item['route']) : $item['url'] }}" title="{{ $item['label'] }}"
                               class="nav-link-app {{ $isActive ? 'active' : '' }}" @if ($isActive) aria-current="page" @endif>
                                <i class="bi {{ $item['icon'] }}"></i>
                                <span class="label">{{ $item['label'] }}</span>
                                @if ($item['key'] === 'inbox' && ($unreadMessages = \App\Modules\Inbox\Models\ContactMessage::unread()->count()))
                                    <span class="badge bg-danger rounded-pill">{{ $unreadMessages }}</span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="sidebar-footer">
            Signed in as <strong class="text-white">{{ auth()->user()->getRoleNames()->map(fn ($r) => \Illuminate\Support\Str::headline($r))->join(', ') ?: 'No role' }}</strong><br>
            Laravel {{ app()->version() }} · PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}
        </div>
    </aside>
    <div class="sidebar-backdrop"></div>

    <div class="app-main">
        <header class="app-topbar">
            <button class="btn btn-icon btn-link text-body" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                <i class="bi bi-list fs-4"></i>
            </button>

            <div class="position-relative d-none d-md-block" style="width: 360px" data-menu-search-wrap>
                <label for="menuSearch" class="visually-hidden">Search pages</label>
                <i class="bi bi-search position-absolute text-secondary" style="left: 12px; top: 11px"></i>
                <input id="menuSearch" type="search" class="form-control ps-5 bg-body-tertiary" placeholder="Search pages…  ( / )" autocomplete="off" data-menu-search>
                <div class="dropdown-menu w-100 mt-1" data-menu-results></div>
            </div>

            <div class="ms-auto d-flex align-items-center gap-1">
                @if (\App\Core\Tenancy\Tenancy::inTenant() && ($saasTenant = \App\Core\Tenancy\Tenancy::tenant()))
                    {{-- SaaS: this tenant's plan and status, links to Billing --}}
                    <a href="{{ Route::has('admin.billing.index') ? route('admin.billing.index') : '#' }}" class="btn btn-sm rounded-pill d-none d-md-inline-flex align-items-center gap-2 me-1 {{ ['trial' => 'btn-outline-info', 'active' => 'btn-outline-success', 'past_due' => 'btn-outline-warning'][$saasTenant->status] ?? 'btn-outline-danger' }}"
                       data-bs-toggle="tooltip" title="Your plan. Click for billing, usage and invoices.">
                        <i class="bi bi-stars"></i>
                        {{ $saasTenant->plan?->name ?? 'No plan' }}
                        <span class="opacity-75">·
                            @if ($saasTenant->status === 'trial')
                                Trial, {{ $saasTenant->trialDaysLeft() }} {{ \Illuminate\Support\Str::plural('day', $saasTenant->trialDaysLeft()) }} left
                            @else
                                {{ \App\Modules\Saas\Models\Tenant::STATUSES[$saasTenant->status] ?? $saasTenant->status }}
                            @endif
                        </span>
                    </a>
                @endif
                <a href="{{ url('/') }}" target="_blank" rel="noopener" class="btn btn-link text-body text-decoration-none d-none d-lg-inline-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-up-right"></i> Visit website
                </a>
                <button class="btn btn-icon btn-link text-body" type="button" data-theme-toggle aria-label="Toggle dark mode">
                    <i class="bi bi-moon" data-theme-icon></i>
                </button>

                @isset($chatUnread)
                    <a href="{{ route('admin.chat.index') }}" class="btn btn-icon btn-link text-body position-relative" aria-label="Team chat" title="Team chat">
                        <i class="bi bi-chat-dots fs-5"></i>
                        <span class="notif-dot {{ $chatUnread ? '' : 'd-none' }}" data-chat-unread="{{ $chatUnread }}">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>
                    </a>
                @endisset

                <div class="dropdown">
                    <button class="btn btn-icon btn-link text-body position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="bi bi-bell fs-5"></i>
                        <span class="notif-dot {{ $notifications->isEmpty() ? 'd-none' : '' }}" data-notif-count="{{ $notifications->count() }}">{{ $notifications->count() }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width: 340px">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                            <div><div class="fw-semibold">Notifications</div><small class="text-secondary">Live via Laravel Reverb</small></div>
                            <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary">Mark all read</button>
                            </form>
                        </div>
                        <div style="max-height: 340px; overflow-y: auto" data-notif-list>
                            @forelse ($notifications as $notification)
                                <a class="notif-item" href="{{ route('admin.notifications.open', $notification->id) }}">
                                    <span class="timeline-dot bg-primary"></span>
                                    <span class="d-flex flex-column">
                                        <span class="fw-semibold">{{ $notification->data['message'] ?? 'Notification' }}</span>
                                        <small class="text-secondary">{{ $notification->created_at->diffForHumans() }}</small>
                                    </span>
                                </a>
                            @empty
                                <div class="p-4 text-center text-secondary" data-notif-empty>You're all caught up.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-link text-body text-decoration-none d-flex align-items-center gap-2 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <x-admin.avatar :user="auth()->user()" size="sm" />
                        <span class="d-none d-sm-flex flex-column align-items-start lh-sm">
                            <span class="fw-semibold small">{{ auth()->user()->name }}</span>
                            <small class="text-secondary">{{ \Illuminate\Support\Str::headline(auth()->user()->getRoleNames()->first() ?? '') }}</small>
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('admin.profile.edit') }}"><i class="bi bi-person me-2"></i>My profile</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.profile.edit', ['tab' => 'security']) }}"><i class="bi bi-shield-lock me-2"></i>Security</a></li>
                        @can('settings.view')
                            <li><a class="dropdown-item" href="{{ route('admin.settings.edit') }}"><i class="bi bi-sliders me-2"></i>Settings</a></li>
                        @endcan
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content" id="main">
            @yield('content')
        </main>

        <footer class="app-footer d-flex flex-wrap justify-content-between gap-2">
            <span>© {{ date('Y') }} {{ setting('site_name', config('app.name')) }}. All rights reserved.</span>
            <span>Laravel Starter v1.0</span>
        </footer>
    </div>
</div>

{{-- Flash messages --}}
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    @foreach (['success' => 'check-circle-fill text-success', 'warning' => 'exclamation-triangle-fill text-warning', 'error' => 'x-circle-fill text-danger'] as $type => $icon)
        @if (session($type))
            <div class="toast align-items-center border-0 shadow" role="status" data-autoshow data-bs-delay="4500">
                <div class="d-flex">
                    <div class="toast-body d-flex gap-2 align-items-center"><i class="bi bi-{{ $icon }}"></i>{{ session($type) }}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif
    @endforeach
    <div class="toast align-items-center border-0 shadow" role="status" id="liveToast">
        <div class="d-flex">
            <div class="toast-body d-flex gap-2 align-items-center"><i class="bi bi-bell-fill text-primary"></i><span data-toast-text></span></div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

{{-- Confirmation dialog used by forms with data-confirm --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px">
        <div class="modal-content text-center p-3">
            <div class="modal-body d-flex flex-column align-items-center gap-2">
                <span class="d-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger" style="width: 52px; height: 52px">
                    <i class="bi bi-exclamation-triangle fs-4"></i>
                </span>
                <h2 class="h5 mb-0" data-confirm-title></h2>
                <p class="text-secondary mb-0" data-confirm-body></p>
            </div>
            <div class="d-flex gap-2 justify-content-center pb-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" data-confirm-ok>Confirm</button>
            </div>
        </div>
    </div>
</div>

{{-- Media picker used by <x-admin.form.media> --}}
@if (module_enabled('media'))
    <div class="modal fade" id="mediaPickerModal" tabindex="-1" aria-hidden="true" data-url="{{ route('admin.media.picker') }}">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Choose from media library</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="search" class="form-control mb-3" placeholder="Search images…" data-picker-search aria-label="Search images">
                    <div class="row g-3" data-picker-grid></div>
                </div>
            </div>
        </div>
    </div>
@endif

@stack('scripts')
</body>
</html>
