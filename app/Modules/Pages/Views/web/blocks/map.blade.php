@php $src = ($data['embed_url'] ?? null) ?: setting('map_embed_url'); @endphp
@if ($src && str_starts_with($src, 'https://www.google.com/maps'))
    <section class="pb-16">
        <div class="container-site">
            @if (! empty($data['heading']))<h2 class="section-title mb-8">{{ $data['heading'] }}</h2>@endif
            <iframe src="{{ $src }}" class="h-96 w-full rounded-3xl border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe>
        </div>
    </section>
@endif
