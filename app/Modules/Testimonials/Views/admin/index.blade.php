@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Testimonials" :breadcrumbs="['Website CMS', 'Testimonials']">
        @can('testimonials.create')
            <a href="{{ route('admin.testimonials.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add testimonial</a>
        @endcan
        @can('testimonials.import')
            <a href="{{ route('admin.testimonials.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i> Import</a>
        @endcan
    </x-admin.page-header>

    {{-- Toolbar, bulk bar and AJAX reload come from the component; the rows are in _table.blade.php --}}
    <x-admin.datatable :table="$table" search="Search testimonials…">
        @include('testimonials::admin._table')
    </x-admin.datatable>
@endsection
