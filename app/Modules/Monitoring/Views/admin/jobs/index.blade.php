@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Failed jobs" :breadcrumbs="['System', 'Monitoring', 'Failed jobs']">
        @can('monitoring.manage')
            @if ($table->rows?->total())
                <x-admin.delete-button :action="route('admin.failed-jobs.flush')" label="Delete all" confirm="Delete every failed job?" body="They will not be retried." button="Delete all" />
                <form method="POST" action="{{ route('admin.failed-jobs.retry-all') }}" class="d-inline" data-confirm="Retry every failed job?" data-confirm-body="All of them go back on the queue." data-confirm-button="Retry all">
                    @csrf
                    <button class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i> Retry all</button>
                </form>
            @endif
        @endcan
    </x-admin.page-header>

    <div class="alert alert-light border d-flex gap-2 align-items-start small">
        <i class="bi bi-info-circle mt-1"></i>
        <span>Background jobs (emails, notifications, backups) that failed after every try. Fix the cause first (e.g. mail settings), then <strong>Retry</strong>. Failed jobs are deleted after 7 days.</span>
    </div>

    <x-admin.datatable :table="$table" search="Job name, error or ID">
        @include('monitoring::admin.jobs._table')
    </x-admin.datatable>
@endsection
