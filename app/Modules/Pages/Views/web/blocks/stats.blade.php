@php
    $items = collect(preg_split('/\r?\n/', (string) ($data['items'] ?? '')))
        ->map(fn ($line) => array_map('trim', explode('|', $line, 2)))
        ->filter(fn ($p) => ($p[0] ?? '') !== '');
@endphp
<section class="bg-brand-600 py-16">
    <div class="container-site">
        @if (! empty($data['heading']))<h2 class="text-center text-2xl font-bold text-white">{{ $data['heading'] }}</h2>@endif
        <dl class="mt-10 grid gap-8 text-center sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($items as [$value, $label])
                <div class="flex flex-col">
                    <dt class="order-2 mt-2 text-brand-100">{{ $label ?? '' }}</dt>
                    <dd class="text-4xl font-bold tracking-tight text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
