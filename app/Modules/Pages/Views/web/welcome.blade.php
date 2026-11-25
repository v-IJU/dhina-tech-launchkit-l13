@extends('layouts.web')

@section('content')
    <section class="section">
        <div class="container-site max-w-2xl text-center">
            <p class="eyebrow">{{ __('Welcome') }}</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-ink">{{ setting('site_name', config('app.name')) }}</h1>
            <p class="mt-4 text-lg text-slate-600">No home page yet. Sign in to the admin panel, open Website CMS › Pages and mark a page as the home page.</p>
            <a href="{{ route('login') }}" class="btn-brand mt-8">{{ __('Open admin panel') }}</a>
        </div>
    </section>
@endsection
