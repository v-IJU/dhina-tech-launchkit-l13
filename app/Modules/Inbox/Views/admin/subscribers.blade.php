@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Newsletter subscribers" :breadcrumbs="['Website CMS', 'Subscribers']">
        @can('subscribers.import')
            <a href="{{ route('admin.subscribers.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i> Import</a>
        @endcan
    </x-admin.page-header>

    <x-admin.datatable :table="$table" search="Search email…">
        @include('inbox::admin._subscribers_table')
    </x-admin.datatable>
@endsection
