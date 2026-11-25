@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Subscriptions" :breadcrumbs="['SaaS', 'Subscriptions']" />

    <x-admin.datatable :table="$table" search="Tenant or gateway ID">
        @include('saas::admin.subscriptions._table')
    </x-admin.datatable>
@endsection
