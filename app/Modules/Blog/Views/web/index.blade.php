@extends('layouts.web')

@section('title', $heading)
@section('meta_description', isset($current) ? ($current->description ?: 'Posts about '.$current->name) : 'News, guides and updates from '.setting('site_name', config('app.name')))

@section('content')
    <section class="border-b border-slate-200 bg-slate-50 py-14">
        <div class="container-site">
            <p class="eyebrow">{{ __('Blog') }}</p>
            <h1 class="mt-2 text-4xl font-bold tracking-tight text-ink">{{ $heading }}</h1>
            <div class="mt-6 flex flex-wrap gap-2">
                <a href="{{ route('blog.index') }}" @class(['rounded-full px-4 py-1.5 text-sm font-semibold', 'bg-brand-600 text-white' => ! isset($current), 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100' => isset($current)])>All</a>
                @foreach ($categories as $category)
                    <a href="{{ route('blog.category', $category) }}" @class(['rounded-full px-4 py-1.5 text-sm font-semibold', 'bg-brand-600 text-white' => isset($current) && $current->is($category), 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100' => ! (isset($current) && $current->is($category))])>
                        {{ $category->name }} <span class="opacity-60">{{ $category->posts_count }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container-site">
            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @forelse ($posts as $post)
                    @include('blog::web._card', ['post' => $post])
                @empty
                    <p class="col-span-full text-slate-500">{{ __('No posts yet. Check back soon.') }}</p>
                @endforelse
            </div>
            <div class="mt-12">{{ $posts->links('pagination::tailwind') }}</div>
        </div>
    </section>
@endsection
