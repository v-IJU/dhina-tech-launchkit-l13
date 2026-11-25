@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Customers" :breadcrumbs="['Customers', 'All customers']">
        @can('customers.import')
            <a href="{{ route('admin.customers.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i> Import</a>
        @endcan
        @can('customers.create')
            <a href="{{ route('admin.customers.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Add customer</a>
        @endcan
    </x-admin.page-header>

    <x-admin.datatable :table="$table" search="Name, email or phone">
        @include('customers::admin._table')
    </x-admin.datatable>
@endsection
