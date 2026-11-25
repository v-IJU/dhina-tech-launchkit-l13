@props(['name', 'label' => null, 'value' => null, 'required' => false, 'hint' => null, 'rows' => 4, 'id' => null])
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" @class(['form-label', 'required' => $required])>{{ $label }}</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" {{ $attributes->except('class')->class(['form-control', 'is-invalid' => $errors->has($key)]) }} @if ($required) required @endif>{{ old($key, $value) }}</textarea>
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($hint)<div class="form-text">{!! $hint !!}</div>@endif
</div>
