@props(['title' => null, 'subtitle' => null, 'flush' => false])
{{-- <x-admin.card title="Details" subtitle="Shown on the profile"> ... <x-slot:actions>…</x-slot:actions> <x-slot:footer>…</x-slot:footer></x-admin.card> --}}
<div {{ $attributes->class(['card']) }}>
    @if ($title || isset($actions))
        <div class="card-header">
            <div>
                @if ($title)<h2>{{ $title }}</h2>@endif
                @if ($subtitle)<small class="text-secondary">{{ $subtitle }}</small>@endif
            </div>
            @isset($actions)<div class="d-flex flex-wrap gap-2 align-items-center">{{ $actions }}</div>@endisset
        </div>
    @endif
    @if ($flush)
        {{ $slot }}
    @else
        <div class="card-body">{{ $slot }}</div>
    @endif
    @isset($footer)
        <div class="card-footer d-flex flex-wrap justify-content-end gap-2">{{ $footer }}</div>
    @endisset
</div>
