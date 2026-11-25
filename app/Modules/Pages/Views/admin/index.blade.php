@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Pages" :breadcrumbs="['Website CMS', 'Pages']">
        @can('pages.create')
            <a href="{{ route('admin.pages.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add page</a>
        @endcan
    </x-admin.page-header>

    <x-admin.datatable :table="$table" search="Search pages…">
        @include('pages::admin._table')
    </x-admin.datatable>
@endsection
