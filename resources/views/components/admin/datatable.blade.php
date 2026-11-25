@props([
    'table',
    'search' => 'Search…',
])
{{--
    Server-side list table (search, filters, date range, sort, per-page, export, bulk actions).
    The rows live in a partial so datatable.js can reload just that part:

    <x-admin.datatable :table="$table" search="Search FAQs…">
        @include('faqs::admin._table')
    </x-admin.datatable>

    Extra controls go in the "toolbar" slot; extra buttons in the "actions" slot.
--}}
@php
    $bulkActions = $table->bulkActions();
@endphp
<div {{ $attributes->class(['card datatable']) }} data-bulk data-datatable>
    @if ($table->hasTrash())
        <div class="datatable-tabs px-3 pt-3">
            <ul class="nav nav-tabs-soft">
                <li class="nav-item"><a href="{{ $table->trashUrl(false) }}" class="nav-link {{ $table->inTrash() ? '' : 'active' }}">All</a></li>
                <li class="nav-item">
                    <a href="{{ $table->trashUrl(true) }}" class="nav-link {{ $table->inTrash() ? 'active' : '' }}">
                        <i class="bi bi-trash3 me-1"></i>Trash <span class="badge badge-soft-secondary">{{ $table->trashCount() }}</span>
                    </a>
                </li>
            </ul>
        </div>
    @endif
    <div class="card-header datatable-toolbar">
        <form method="GET" action="{{ $table->resetUrl() }}" class="d-flex flex-wrap gap-2 align-items-end w-100" data-datatable-form role="search">
            @foreach (['sort', 'dir', 'per_page', 'trashed'] as $keep)
                @if (request()->filled($keep))
                    <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">
                @endif
            @endforeach

            @if ($table->hasSearch())
                <div style="flex: 1 1 200px; max-width: 300px">
                    <label class="form-label small text-secondary mb-1" for="search_q">Search</label>
                    <x-admin.search :placeholder="$search" />
                </div>
            @endif

            @foreach ($table->filters() as $name => $filter)
                <div>
                    <label class="form-label small text-secondary mb-1" for="dt_{{ $name }}">{{ $filter['label'] }}</label>
                    <select name="{{ $name }}" id="dt_{{ $name }}" class="form-select" style="min-width: 130px" data-datatable-filter>
                        <option value="">All</option>
                        @foreach ($filter['options'] as $value => $label)
                            <option value="{{ $value }}" @selected((string) $filter['value'] === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            @if ($table->hasDateRange())
                <div>
                    <label class="form-label small text-secondary mb-1" for="dt_from">{{ $table->dateLabel() }} from</label>
                    <input type="date" name="from" id="dt_from" value="{{ request('from') }}" class="form-control" data-datatable-filter>
                </div>
                <div>
                    <label class="form-label small text-secondary mb-1" for="dt_to">to</label>
                    <input type="date" name="to" id="dt_to" value="{{ request('to') }}" class="form-control" data-datatable-filter>
                </div>
            @endif

            {{ $toolbar ?? '' }}

            <noscript><button class="btn btn-outline-secondary">Apply</button></noscript>
            <a href="{{ $table->resetUrl() }}" class="btn btn-link text-secondary px-1 {{ $table->isFiltered() ? '' : 'd-none' }}" data-datatable-reset>
                <i class="bi bi-x-circle me-1"></i>Reset
            </a>

            <div class="d-flex gap-2 ms-auto">
                {{ $actions ?? '' }}
                @if ($table->canExport())
                    <a href="{{ $table->exportUrl() }}" class="btn btn-outline-secondary text-nowrap" data-datatable-export>
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                @endif
            </div>
        </form>
    </div>

    @if ($bulkActions && $table->bulkUrl())
        <form method="POST" action="{{ $table->bulkUrl() }}" data-bulk-bar class="d-none d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom bg-primary-subtle">
            @csrf
            <span data-bulk-ids></span>
            <strong class="me-2"><span data-bulk-count>0</span> selected</strong>
            @foreach ($bulkActions as $action => $button)
                <button name="action" value="{{ $action }}" class="btn btn-sm {{ $button['class'] }}"
                    @if ($action === 'delete') data-confirm="Delete the selected items?" data-confirm-body="They go to the Trash and can be restored." data-confirm-button="Delete" @endif
                    @if ($action === 'force-delete') data-confirm="Delete the selected items forever?" data-confirm-body="This cannot be undone." data-confirm-button="Delete forever" @endif>
                    <i class="bi {{ $button['icon'] }} me-1"></i>{{ $button['label'] }}
                </button>
            @endforeach
            <button type="button" class="btn btn-sm btn-link" data-bulk-clear>Clear</button>
        </form>
    @endif

    <div class="datatable-body" data-datatable-body aria-live="polite">
        {{ $slot }}
    </div>
</div>
