<article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-lg">
    <a href="{{ route('blog.show', $post) }}" class="block aspect-[16/9] overflow-hidden bg-gradient-to-br from-brand-100 to-brand-50">
        @if ($post->featured_image)
            <img src="{{ $post->featured_image }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
        @endif
    </a>
    <div class="flex flex-1 flex-col p-6">
        <div class="flex items-center gap-3 text-sm text-slate-500">
            @if ($post->category)
                <a href="{{ route('blog.category', $post->category) }}" class="rounded-full bg-brand-50 px-2.5 py-0.5 font-semibold text-brand-700">{{ $post->category->name }}</a>
            @endif
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('d M Y') }}</time>
        </div>
        <h3 class="mt-3 text-lg leading-snug font-semibold text-ink"><a href="{{ route('blog.show', $post) }}" class="hover:text-brand-700">{{ $post->title }}</a></h3>
        <p class="mt-2 flex-1 text-slate-600">{{ $post->summary(140) }}</p>
        <a href="{{ route('blog.show', $post) }}" class="mt-4 text-sm font-semibold text-brand-700">{{ __('Read more') }} &rarr;</a>
    </div>
</article>
