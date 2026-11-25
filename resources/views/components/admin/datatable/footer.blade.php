@props(['table', 'label' => 'entries'])
{{-- Footer: "Show [25] per page", "Showing 1–25 of 140", page links. Lives inside the table partial. --}}
@php $rows = $table->rows; @endphp
<div class="card-footer datatable-footer d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div class="d-flex align-items-center gap-3 text-secondary small">
        <label class="d-flex align-items-center gap-2 mb-0">
            Show
            <select class="form-select form-select-sm" style="width: auto" data-datatable-per-page aria-label="Rows per page">
                @foreach ($table->perPageOptions() as $n)
                    <option value="{{ $table->url(['per_page' => $n, 'page' => null]) }}" @selected($table->currentPerPage() === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </label>
        <span>
            @if ($rows->total())
                Showing {{ number_format($rows->firstItem()) }}–{{ number_format($rows->lastItem()) }} of {{ number_format($rows->total()) }} {{ $label }}
            @else
                No {{ $label }}
            @endif
        </span>
    </div>
    @if ($rows->hasPages())
        <div data-datatable-pages>{{ $rows->onEachSide(1)->links() }}</div>
    @endif
</div>
