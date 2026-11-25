@props(['name', 'label', 'checked' => false, 'hint' => null, 'id' => null, 'value' => 1])
{{-- A switch. A hidden 0 is sent when unchecked so the value always reaches the controller. --}}
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $isChecked = (bool) old($key, $checked);
@endphp
<div {{ $attributes->class(['form-check form-switch']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked($isChecked)>
    <label class="form-check-label" for="{{ $id }}">
        <span class="fw-medium">{{ $label }}</span>
        @if ($hint)<span class="d-block small text-secondary">{{ $hint }}</span>@endif
    </label>
</div>
