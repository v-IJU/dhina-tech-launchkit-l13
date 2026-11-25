@php $imageRight = ($data['image_position'] ?? 'left') === 'right'; @endphp
<section class="section">
    <div class="container-site grid items-center gap-12 lg:grid-cols-2">
        <div @class(['lg:order-2' => $imageRight])>
            @if (! empty($data['image']))
                <img src="{{ $data['image'] }}" alt="" class="w-full rounded-3xl object-cover shadow-lg" loading="lazy">
            @else
                <div class="aspect-[4/3] rounded-3xl bg-gradient-to-br from-brand-100 to-brand-50"></div>
            @endif
        </div>
        <div>
            @if (! empty($data['heading']))<h2 class="section-title">{{ $data['heading'] }}</h2>@endif
            @if (! empty($data['body']))<div class="prose-site mt-6">{!! $data['body'] !!}</div>@endif
            @if (! empty($data['button_text']))<a href="{{ $data['button_url'] ?: '#' }}" class="btn-brand mt-4">{{ $data['button_text'] }}</a>@endif
        </div>
    </div>
</section>
