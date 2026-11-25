@props(['user' => null, 'name' => null, 'size' => null])
@php
    $label = $user?->name ?? $name ?? '?';
    $url = $user?->avatar_url;
@endphp
<span {{ $attributes->class(['avatar', 'avatar-'.$size => $size]) }} style="background: {{ avatar_color($label) }}" aria-hidden="true">
    @if ($url)
        <img src="{{ $url }}" alt="">
    @else
        {{ initials($label) }}
    @endif
</span>
