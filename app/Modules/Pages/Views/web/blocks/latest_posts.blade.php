@php
    $posts = module_enabled('blog')
        ? \App\Modules\Blog\Models\Post::query()->published()->with(['category', 'translations'])->latest('published_at')->limit(max(1, min(12, (int) ($data['count'] ?? 3))))->get()->map->localized()
        : collect();
@endphp
@if ($posts->isNotEmpty())
    <section class="section">
        <div class="container-site">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="section-title">{{ $data['heading'] ?: __('Latest from the blog') }}</h2>
                <a href="{{ route('blog.index') }}" class="font-semibold text-brand-700">{{ __('All posts') }} &rarr;</a>
            </div>
            <div class="mt-10 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @include('blog::web._card', ['post' => $post])
                @endforeach
            </div>
        </div>
    </section>
@endif
