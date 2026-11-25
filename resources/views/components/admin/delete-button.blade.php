@props(['action', 'confirm' => 'Delete this item?', 'body' => 'This action cannot be undone.', 'label' => null, 'button' => 'Delete'])
{{-- Delete with a confirmation dialog. --}}
<form method="POST" action="{{ $action }}" class="d-inline" data-confirm="{{ $confirm }}" data-confirm-body="{{ $body }}" data-confirm-button="{{ $button }}">
    @csrf
    @method('DELETE')
    @if ($label)
        <button {{ $attributes->class(['btn btn-sm btn-outline-danger']) }}>{{ $label }}</button>
    @else
        <button {{ $attributes->class(['btn btn-sm btn-icon btn-outline-danger']) }} aria-label="Delete" data-bs-toggle="tooltip" title="Delete"><i class="bi bi-trash"></i></button>
    @endif
</form>
