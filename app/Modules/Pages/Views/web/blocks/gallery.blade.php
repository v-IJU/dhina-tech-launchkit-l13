@php $images = array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($data['images'] ?? '')))); @endphp
@if ($images)
    <section class="section">
        <div class="container-site">
            @if (! empty($data['heading']))<h2 class="section-title mb-10">{{ $data['heading'] }}</h2>@endif
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                @foreach ($images as $src)
                    @if (preg_match('#^(https?://|/)#', $src))
                        <a href="{{ $src }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-2xl">
                            <img src="{{ $src }}" alt="" class="aspect-[4/3] w-full object-cover transition duration-300 hover:scale-105" loading="lazy">
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endif
