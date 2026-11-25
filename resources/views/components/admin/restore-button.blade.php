@props(['action', 'label' => null])
{{-- Restore a soft-deleted row from the trash. --}}
<form method="POST" action="{{ $action }}" class="d-inline">
    @csrf
    @method('PATCH')
    @if ($label)
        <button {{ $attributes->class(['btn btn-sm btn-outline-success']) }}>{{ $label }}</button>
    @else
        <button {{ $attributes->class(['btn btn-sm btn-icon btn-outline-success']) }} aria-label="Restore" data-bs-toggle="tooltip" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
    @endif
</form>
