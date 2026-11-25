@props(['placeholder' => 'Search…', 'name' => 'q'])
<div {{ $attributes->class(['position-relative']) }}>
    <label for="search_{{ $name }}" class="visually-hidden">{{ $placeholder }}</label>
    <i class="bi bi-search position-absolute text-secondary" style="left: 12px; top: 11px"></i>
    <input type="search" id="search_{{ $name }}" name="{{ $name }}" value="{{ request($name) }}" class="form-control ps-5" placeholder="{{ $placeholder }}">
</div>
