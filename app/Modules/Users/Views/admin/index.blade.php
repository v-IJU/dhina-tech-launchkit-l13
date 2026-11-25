@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Users" :breadcrumbs="['Access control', 'Users']">
        @can('users.import')
            <a href="{{ route('admin.users.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i> Import</a>
        @endcan
        @can('users.create')
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Add user</a>
        @endcan
    </x-admin.page-header>

    <x-admin.datatable :table="$table" search="Name, email or username">
        @include('users::admin._table')
    </x-admin.datatable>
@endsection
