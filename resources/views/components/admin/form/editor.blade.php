@props(['name', 'label' => null, 'value' => null, 'required' => false, 'hint' => null, 'id' => null])
{{-- Rich text editor (Trix). HTML is cleaned with HTML Purifier on save. --}}
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" @class(['form-label', 'required' => $required])>{{ $label }}</label>
    @endif
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ old($key, $value) }}">
    <trix-editor input="{{ $id }}" @class(['form-control trix-content', 'is-invalid' => $errors->has($key)]) aria-label="{{ $label }}"></trix-editor>
    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if ($hint)<div class="form-text">{!! $hint !!}</div>@endif
</div>
