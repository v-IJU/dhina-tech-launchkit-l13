@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Tenants" :breadcrumbs="['SaaS', 'Tenants']">
        @can('saas.create')
            <a href="{{ route('admin.saas.tenants.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Onboard tenant</a>
        @endcan
    </x-admin.page-header>

    <x-admin.datatable :table="$table" search="Name, email, subdomain or domain">
        @include('saas::admin.tenants._table')
    </x-admin.datatable>
@endsection
