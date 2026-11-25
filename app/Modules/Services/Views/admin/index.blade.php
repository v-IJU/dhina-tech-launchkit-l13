@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Services" :breadcrumbs="['Website CMS', 'Services']">
        @can('services.create')
            <a href="{{ route('admin.services.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add service</a>
        @endcan
        @can('services.import')
            <a href="{{ route('admin.services.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i> Import</a>
        @endcan
    </x-admin.page-header>

    {{-- Toolbar, bulk bar and AJAX reload come from the component; the rows are in _table.blade.php --}}
    <x-admin.datatable :table="$table" search="Search services…">
        @include('services::admin._table')
    </x-admin.datatable>
@endsection
