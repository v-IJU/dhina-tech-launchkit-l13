@props(['name', 'label' => null, 'url' => null, 'hint' => 'JPG, PNG or WebP, up to 4 MB.', 'id' => null])
{{-- Direct file upload with live preview. Stored on the public disk by the controller. --}}
@php $id = $id ?? 'f_'.$name; @endphp
<div {{ $attributes->class(['d-flex flex-column gap-2']) }}>
    @if ($label)<label for="{{ $id }}" class="form-label mb-0">{{ $label }}</label>@endif
    <div class="image-preview" id="{{ $id }}_preview">
        @if ($url)
            <img src="{{ $url }}" alt="">
        @else
            <span><i class="bi bi-image fs-3"></i></span>
        @endif
    </div>
    <input type="file" name="{{ $name }}" id="{{ $id }}" accept="image/*" data-image-preview="#{{ $id }}_preview"
           @class(['form-control form-control-sm', 'is-invalid' => $errors->has($name)])>
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if ($hint)<div class="form-text mt-0">{{ $hint }}</div>@endif
</div>
