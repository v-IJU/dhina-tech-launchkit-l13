<section class="py-16">
    <div class="container-site">
        <div class="relative overflow-hidden rounded-3xl bg-brand-600 px-8 py-14 text-center shadow-xl sm:px-16">
            @if (! empty($data['heading']))<h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $data['heading'] }}</h2>@endif
            @if (! empty($data['text']))<p class="mx-auto mt-4 max-w-xl text-lg text-brand-100">{{ $data['text'] }}</p>@endif
            @if (! empty($data['button_text']))
                <a href="{{ $data['button_url'] ?: '#' }}" class="mt-8 inline-flex items-center justify-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-50">{{ $data['button_text'] }}</a>
            @endif
        </div>
    </div>
</section>
