@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'prefix' => null, 'id' => null])
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = $type === 'password' ? '' : old($key, $value);
@endphp
<div {{ $attributes->only('class')->class(['mb-0']) }}>
    @if ($label)
        <label for="{{ $id }}" @class(['form-label', 'required' => $required])>{{ $label }}</label>
    @endif
    <div @class(['input-group' => $prefix])>
        @if ($prefix)<span class="input-group-text text-secondary small">{{ $prefix }}</span>@endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}"
               {{ $attributes->except('class')->class(['form-control', 'is-invalid' => $errors->has($key)]) }}
               @if ($required) required @endif>
        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @if ($hint)<div class="form-text">{!! $hint !!}</div>@endif
</div>
