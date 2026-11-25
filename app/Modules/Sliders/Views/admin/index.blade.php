@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Sliders" :breadcrumbs="['Website CMS', 'Sliders']">
        @can('sliders.create')
            <a href="{{ route('admin.sliders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add slider</a>
        @endcan
        @can('sliders.import')
            <a href="{{ route('admin.sliders.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i> Import</a>
        @endcan
    </x-admin.page-header>

    {{-- Toolbar, bulk bar and AJAX reload come from the component; the rows are in _table.blade.php --}}
    <x-admin.datatable :table="$table" search="Search sliders…">
        @include('sliders::admin._table')
    </x-admin.datatable>
@endsection
