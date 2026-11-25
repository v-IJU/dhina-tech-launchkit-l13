@props(['table', 'sort' => null])
{{-- Column heading. With sort="column" it becomes a sort link: <x-admin.datatable.th :table="$table" sort="name">Name</x-admin.datatable.th> --}}
@if ($sort && $table->isSortable($sort))
    @php
        $active = $table->currentSort() === $sort;
        $dir = $active ? $table->currentDirection() : null;
    @endphp
    <th {{ $attributes->class(['sortable', 'sorted' => $active]) }} aria-sort="{{ $dir === 'asc' ? 'ascending' : ($dir === 'desc' ? 'descending' : 'none') }}">
        <a href="{{ $table->sortUrl($sort) }}" data-datatable-link>
            {{ $slot }}
            <i class="bi {{ $dir === 'asc' ? 'bi-sort-up' : ($dir === 'desc' ? 'bi-sort-down' : 'bi-arrow-down-up') }}"></i>
        </a>
    </th>
@else
    <th {{ $attributes }}>{{ $slot }}</th>
@endif
