@extends('layouts.admin')

@section('content')
    <x-admin.page-header :title="class_basename($job->jobName())" :breadcrumbs="['System', 'Failed jobs' => route('admin.failed-jobs.index'), '#'.$job->id]">
        <a href="{{ route('admin.failed-jobs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
        @can('monitoring.manage')
            <x-admin.delete-button :action="route('admin.failed-jobs.destroy', $job)" label="Delete" confirm="Delete this failed job?" body="It will not be retried." button="Delete" class="btn-outline-danger" />
            <form method="POST" action="{{ route('admin.failed-jobs.retry', $job) }}" class="d-inline">
                @csrf
                <button class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i> Retry</button>
            </form>
        @endcan
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8 d-flex flex-column gap-4">
            <x-admin.card title="Error" flush>
                <pre class="mb-0 p-4 small bg-body-tertiary text-danger-emphasis" style="max-height: 520px; overflow: auto">{{ $job->exception }}</pre>
            </x-admin.card>
            <x-admin.card title="Payload" subtitle="The data the job was given." flush>
                <pre class="mb-0 p-4 small bg-body-tertiary" style="max-height: 360px; overflow: auto">{{ json_encode($job->payloadData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            <x-admin.card title="Details">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-secondary fw-normal">Job</dt><dd class="col-7 code" style="word-break: break-all">{{ $job->jobName() }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Queue</dt><dd class="col-7">{{ $job->queue }} <span class="text-secondary">({{ $job->connection }})</span></dd>
                    <dt class="col-5 text-secondary fw-normal">Failed</dt><dd class="col-7">{{ $job->failed_at?->format('d M Y, H:i:s') }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Attempts</dt><dd class="col-7">{{ $job->attempts() }}</dd>
                    <dt class="col-5 text-secondary fw-normal mb-0">ID</dt><dd class="col-7 code mb-0" style="word-break: break-all">{{ $job->uuid }}</dd>
                </dl>
            </x-admin.card>
        </div>
    </div>
@endsection
