@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Customer sign-in history" :breadcrumbs="['Customers', 'Sign-in history']" />

    <x-admin.datatable :table="$table" search="Email, IP address or browser">
        @include('customers::admin.logins._table')
    </x-admin.datatable>
@endsection
