@props(['name', 'label' => null, 'value' => null, 'hint' => null])
{{-- Pick an image from the Media library. Stores the image URL. --}}
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($key, $value);
@endphp
<div {{ $attributes->class(['d-flex flex-column gap-2']) }} data-media-field>
    @if ($label)<span class="form-label mb-0">{{ $label }}</span>@endif
    <input type="hidden" name="{{ $name }}" value="{{ $current }}">
    <div class="image-preview">
        @if ($current)
            <img src="{{ $current }}" alt="">
        @else
            <span><i class="bi bi-image fs-3"></i></span>
        @endif
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" data-media-open><i class="bi bi-images me-1"></i> Choose from media</button>
        <button type="button" class="btn btn-sm btn-link text-secondary" data-media-clear>Remove</button>
    </div>
    @error($key)<div class="text-danger small">{{ $message }}</div>@enderror
    @if ($hint)<div class="form-text mt-0">{{ $hint }}</div>@endif
</div>
