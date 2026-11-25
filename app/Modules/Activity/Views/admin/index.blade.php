@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Activity log" :breadcrumbs="['System', 'Activity log']">
        @can('activity.delete')
            <form method="POST" action="{{ route('admin.activity.prune') }}" class="d-flex gap-2" data-confirm="Delete old log entries?" data-confirm-button="Delete">
                @csrf @method('DELETE')
                <select name="days" class="form-select" aria-label="Older than">
                    <option value="30">Older than 30 days</option>
                    <option value="90" selected>Older than 90 days</option>
                    <option value="365">Older than 1 year</option>
                </select>
                <button class="btn btn-outline-danger text-nowrap">Clean up</button>
            </form>
        @endcan
    </x-admin.page-header>

    <x-admin.datatable :table="$table" search="Search activity…">
        @include('activity::admin._table')
    </x-admin.datatable>
@endsection
