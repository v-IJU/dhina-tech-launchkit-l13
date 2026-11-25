@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Invoices" :breadcrumbs="['SaaS', 'Invoices']" />

    <x-admin.datatable :table="$table" search="Invoice number, payment ID or tenant">
        @include('saas::admin.invoices._table')
    </x-admin.datatable>
@endsection
