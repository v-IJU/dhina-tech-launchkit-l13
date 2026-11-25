@props(['icon' => 'bi-inbox', 'message' => 'Nothing here yet.'])
<div {{ $attributes->class(['empty-state']) }}>
    <i class="bi {{ $icon }}"></i>
    <div>{{ $message }}</div>
    @if (trim($slot))<div class="mt-3">{{ $slot }}</div>@endif
</div>
