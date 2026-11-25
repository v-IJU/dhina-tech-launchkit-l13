{{-- My account shell: page heading + side navigation. Child views fill @section('account'). --}}
@extends('layouts.web')

@push('head')<meta name="robots" content="noindex">@endpush

@php
    $me = auth('customer')->user();
    $nav = [
        'customer.account' => [__('Profile'), 'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm-9 9a9 9 0 0 1 18 0'],
        'customer.addresses.index' => [__('Addresses'), 'M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11zm0-8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z'],
        'customer.security' => [__('Security'), 'M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3z'],
    ];
@endphp

@section('content')
    <section class="border-b border-slate-200 bg-slate-50 py-10">
        <div class="container-site flex items-center gap-4">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-lg font-bold text-white">{{ initials($me->name) }}</span>
            <div>
                <p class="eyebrow">{{ __('My account') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ $me->name }}</h1>
            </div>
        </div>
    </section>

    <section class="py-10 sm:py-14">
        <div class="container-site grid gap-8 lg:grid-cols-[220px_1fr]">
            <nav aria-label="{{ __('My account') }}" class="flex gap-2 overflow-x-auto lg:flex-col">
                @foreach ($nav as $route => [$label, $icon])
                    @php $active = request()->routeIs($route, str_replace('.index', '.*', $route)); @endphp
                    <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                       @class(['flex items-center gap-3 whitespace-nowrap rounded-lg px-3 py-2.5 text-sm font-semibold transition',
                           'bg-brand-50 text-brand-700' => $active, 'text-slate-700 hover:bg-slate-50' => ! $active])>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach
                <form method="POST" action="{{ route('customer.logout') }}" class="lg:mt-4 lg:border-t lg:border-slate-200 lg:pt-4">
                    @csrf
                    <button class="flex w-full items-center gap-3 whitespace-nowrap rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        {{ __('Sign out') }}
                    </button>
                </form>
            </nav>

            <div class="min-w-0 space-y-8">
                @if (session('status'))
                    <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
                @endif
                @yield('account')
            </div>
        </div>
    </section>
@endsection
