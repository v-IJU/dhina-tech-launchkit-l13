@props(['table'])
{{-- "Select all" header cell; only shown when the user has a bulk action available. --}}
@if ($table->bulkUrl())
    <th style="width: 40px"><input type="checkbox" class="form-check-input" data-check-all aria-label="Select all rows"></th>
@endif
