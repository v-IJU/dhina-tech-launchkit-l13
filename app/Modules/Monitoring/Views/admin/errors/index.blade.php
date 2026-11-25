@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Error log" :breadcrumbs="['System', 'Monitoring', 'Error log']">
        @can('monitoring.manage')
            <x-admin.delete-button :action="route('admin.errors.clear-resolved')" label="Delete fixed errors" confirm="Delete every error marked as fixed?" body="Open errors are kept." button="Delete fixed" />
        @endcan
    </x-admin.page-header>

    <div class="alert alert-light border d-flex gap-2 align-items-start small">
        <i class="bi bi-info-circle mt-1"></i>
        <span>Every server error is saved here once, with a counter for repeats. You get a bell notification (and an email, if set in <strong>Settings → Monitoring</strong>) the first time an error appears and when a fixed one comes back. Visitors' 404s and form validation errors are not errors and are never listed.</span>
    </div>

    <x-admin.datatable :table="$table" search="Message, class, URL or file">
        @include('monitoring::admin.errors._table')
    </x-admin.datatable>
@endsection
