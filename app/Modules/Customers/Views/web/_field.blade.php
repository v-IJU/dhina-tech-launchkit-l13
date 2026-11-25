{{--
    One labelled input for the customer pages.
    @include('customers::web._field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'value' => $customer->email, 'bag' => 'profile'])
    Optional keys: type, value, bag, required, autocomplete, hint, optional, class, inputmode
--}}
@php
    $type ??= 'text';
    $bag ??= 'default';
    $id ??= 'f_'.$name;
    $error = $errors->getBag($bag)->first($name);
@endphp
<div class="{{ $class ?? '' }}">
    <label for="{{ $id }}" class="field-label">{{ $label }} @if (! empty($optional))<span class="font-normal text-slate-400">{{ __('(optional)') }}</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ $type === 'password' ? '' : old($name, $value ?? '') }}"
           @if (! empty($required)) required @endif
           @if (! empty($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
           @if (! empty($inputmode)) inputmode="{{ $inputmode }}" @endif
           @if ($error) aria-invalid="true" aria-describedby="{{ $id }}_error" @endif
           @class(['field', '!border-red-400' => $error])>
    @if ($error)
        <p id="{{ $id }}_error" class="mt-1.5 text-sm text-red-600">{{ $error }}</p>
    @elseif (! empty($hint))
        <p class="mt-1.5 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
