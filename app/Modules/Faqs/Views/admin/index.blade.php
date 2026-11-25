@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="FAQs" :breadcrumbs="['Website CMS', 'FAQs']">
        @can('faqs.create')
            <a href="{{ route('admin.faqs.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add FAQ</a>
        @endcan
        @can('faqs.import')
            <a href="{{ route('admin.faqs.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i> Import</a>
        @endcan
    </x-admin.page-header>

    {{-- Toolbar, bulk bar and AJAX reload come from the component; the rows are in _table.blade.php --}}
    <x-admin.datatable :table="$table" search="Search faqs…">
        @include('faqs::admin._table')
    </x-admin.datatable>
@endsection
