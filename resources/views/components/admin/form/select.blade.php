@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'hint' => null, 'id' => null])
{{-- options: ['value' => 'Label'] --}}
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = (string) old($key, $value);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" @class(['form-label', 'required' => $required])>{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" {{ $attributes->except('class')->class(['form-select', 'is-invalid' => $errors->has($key)]) }} @if ($required) required @endif>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($hint)<div class="form-text">{!! $hint !!}</div>@endif
</div>
