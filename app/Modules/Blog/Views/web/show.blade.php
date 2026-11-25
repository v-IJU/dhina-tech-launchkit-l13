@extends('layouts.web')

@section('title', $post->meta_title ?: $post->title)
@section('meta_description', $post->meta_description ?: $post->summary())
@section('og_image', $post->featured_image ?: '')
@section('og_type', 'article')

@push('head')
    <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode(array_filter([
        '@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post->title, 'description' => $post->summary(),
        'image' => $post->featured_image, 'datePublished' => $post->published_at?->toAtomString(), 'dateModified' => $post->updated_at->toAtomString(),
        'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : null,
        'publisher' => ['@type' => 'Organization', 'name' => setting('site_name', config('app.name'))],
        'mainEntityOfPage' => route('blog.show', $post),
    ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    @unless ($post->isLive())
        <div class="bg-amber-50 text-amber-800"><div class="container-site py-2 text-sm font-medium">Preview — this post is not live yet ({{ $post->status }}).</div></div>
    @endunless

    <article class="py-14 sm:py-20">
        <header class="container-site max-w-3xl">
            <a href="{{ route('blog.index') }}" class="text-sm font-semibold text-brand-700">&larr; All posts</a>
            <div class="mt-6 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                @if ($post->category)<a href="{{ route('blog.category', $post->category) }}" class="rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-700">{{ $post->category->name }}</a>@endif
                <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('d F Y') }}</time>
                <span aria-hidden="true">·</span>
                <span>{{ __(':minutes min read', ['minutes' => $post->readingMinutes()]) }}</span>
            </div>
            <h1 class="mt-4 text-4xl leading-tight font-bold tracking-tight text-ink sm:text-5xl">{{ $post->title }}</h1>
            @if ($post->excerpt)<p class="mt-5 text-xl text-slate-600">{{ $post->excerpt }}</p>@endif
            @if ($post->author)
                <div class="mt-8 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full bg-brand-100 font-semibold text-brand-700">
                        @if ($post->author->avatar_url)<img src="{{ $post->author->avatar_url }}" alt="" class="h-full w-full object-cover">@else{{ initials($post->author->name) }}@endif
                    </span>
                    <div>
                        <div class="font-semibold text-ink">{{ $post->author->name }}</div>
                        @if ($post->author->job_title)<div class="text-sm text-slate-500">{{ $post->author->job_title }}</div>@endif
                    </div>
                </div>
            @endif
        </header>

        @if ($post->featured_image)
            <div class="container-site mt-10 max-w-5xl">
                <img src="{{ $post->featured_image }}" alt="" class="w-full rounded-3xl object-cover shadow-lg">
            </div>
        @endif

        <div class="container-site mt-12 max-w-3xl">
            <div class="prose-site">{!! $post->content !!}</div>
            @if ($post->tags)
                <div class="mt-10 flex flex-wrap gap-2 border-t border-slate-200 pt-6">
                    @foreach ($post->tags as $tag)
                        <a href="{{ route('blog.index', ['tag' => $tag]) }}" class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700 hover:bg-slate-200">#{{ $tag }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="border-t border-slate-200 bg-slate-50 py-16">
            <div class="container-site">
                <h2 class="text-2xl font-bold text-ink">{{ __('Related posts') }}</h2>
                <div class="mt-8 grid gap-8 md:grid-cols-3">
                    @foreach ($related as $item)
                        @include('blog::web._card', ['post' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
