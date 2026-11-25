@props(['table', 'value', 'disabled' => false])
{{-- Row checkbox for bulk actions; only shown when the user has a bulk action available. --}}
@if ($table->bulkUrl())
    <td><input type="checkbox" class="form-check-input" data-row-check value="{{ $value }}" @disabled($disabled) aria-label="Select row {{ $value }}"></td>
@endif
