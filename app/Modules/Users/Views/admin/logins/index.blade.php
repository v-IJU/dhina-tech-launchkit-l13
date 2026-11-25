@extends('layouts.admin')

@section('title', 'Sign-in history')

@section('content')
    <x-admin.page-header title="Sign-in history" :breadcrumbs="['Access control', 'Users', 'Sign-in history']" />

    <x-admin.datatable :table="$table" search="Email, IP or browser…">
        @include('users::admin.logins._table')
    </x-admin.datatable>
@endsection
