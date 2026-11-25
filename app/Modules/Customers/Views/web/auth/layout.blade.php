{{-- Centered card used by sign in, sign up, two-factor, forgot/reset password and verify email. --}}
@extends('layouts.web')

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <section class="bg-slate-50 py-14 sm:py-20">
        <div class="mx-auto w-full max-w-md px-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-8">
                <h1 class="text-2xl font-bold tracking-tight text-ink">@yield('heading')</h1>
                @hasSection('intro')<p class="mt-2 text-sm leading-relaxed text-slate-600">@yield('intro')</p>@endif
                @if (session('status'))
                    <div class="mt-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
                @endif
                <div class="mt-6">@yield('form')</div>
            </div>
            @hasSection('below')<div class="mt-6 text-center text-sm text-slate-600">@yield('below')</div>@endif
        </div>
    </section>
@endsection
