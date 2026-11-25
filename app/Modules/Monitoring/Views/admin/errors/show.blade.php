@extends('layouts.admin')

@php $where = ['staff' => 'Admin panel (staff)', 'customer' => 'Website / app (customer)', 'api' => 'Staff API', 'console' => 'Console / queue job']; @endphp

@section('content')
    <x-admin.page-header :title="$error->shortClass()" :breadcrumbs="['System', 'Error log' => route('admin.errors.index'), '#'.$error->id]">
        <a href="{{ route('admin.errors.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
        @can('logs.view')
            {{-- Same error in the log files: full context, surrounding log lines, and repeats --}}
            <a href="{{ route('log-viewer.index', ['query' => \Illuminate\Support\Str::limit($error->message, 60, '')]) }}" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-file-earmark-text me-1"></i> Find in log files</a>
        @endcan
        @can('monitoring.manage')
            <x-admin.delete-button :action="route('admin.errors.destroy', $error)" label="Delete" confirm="Delete this error?" body="If it happens again it is recorded as a new error." button="Delete" class="btn-outline-danger" />
            <form method="POST" action="{{ route('admin.errors.resolve', $error) }}" class="d-inline">
                @csrf
                @if ($error->resolved_at)
                    <button class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i> Reopen</button>
                @else
                    <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> Mark as fixed</button>
                @endif
            </form>
        @endcan
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8 d-flex flex-column gap-4">
            <x-admin.card>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @if ($error->resolved_at)
                        <span class="badge badge-soft-success">Fixed {{ $error->resolved_at->diffForHumans() }}</span>
                    @else
                        <span class="badge badge-soft-danger">Open</span>
                    @endif
                    <span class="badge badge-soft-secondary">{{ number_format($error->occurrences) }} {{ \Illuminate\Support\Str::plural('time', $error->occurrences) }}</span>
                </div>
                <p class="fs-6 fw-semibold mb-2" style="word-break: break-word">{{ $error->message ?: '(no message)' }}</p>
                <div class="code small text-secondary mb-1">{{ $error->class }}</div>
                <div class="code small">{{ $error->location() }}</div>
            </x-admin.card>

            <x-admin.card title="Stack trace" subtitle="Most recent call first. Paths are relative to the project folder." flush>
                <pre class="mb-0 p-4 small bg-body-tertiary" style="max-height: 560px; overflow: auto; white-space: pre">{{ $error->trace ?: 'No trace recorded.' }}</pre>
            </x-admin.card>
        </div>

        <div class="col-xl-4 d-flex flex-column gap-4">
            <x-admin.card title="Last time it happened">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-secondary fw-normal">When</dt><dd class="col-7">{{ $error->last_seen_at?->format('d M Y, H:i:s') }}<br><span class="text-secondary">{{ $error->last_seen_at?->diffForHumans() }}</span></dd>
                    <dt class="col-5 text-secondary fw-normal">Where</dt><dd class="col-7">{{ $where[$error->user_type] ?? 'Visitor' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Request</dt><dd class="col-7 code" style="word-break: break-all">{{ $error->method }} {{ $error->url ?? '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Account</dt>
                    <dd class="col-7">
                        @if ($error->user_id && in_array($error->user_type, ['staff', 'api'], true) && Route::has('admin.users.edit'))
                            <a href="{{ route('admin.users.edit', $error->user_id) }}">Staff user #{{ $error->user_id }}</a>
                        @elseif ($error->user_id && $error->user_type === 'customer' && module_enabled('customers'))
                            <a href="{{ route('admin.customers.show', $error->user_id) }}">Customer #{{ $error->user_id }}</a>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-5 text-secondary fw-normal">IP address</dt><dd class="col-7 code">{{ $error->ip_address ?? '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal mb-0">First seen</dt><dd class="col-7 mb-0">{{ $error->first_seen_at?->format('d M Y, H:i') }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="How to fix">
                <ol class="small mb-0 ps-3 d-flex flex-column gap-1">
                    <li>Open <code>{{ $error->location() }}</code> and read the stack trace for the first line in <code>app/</code>.</li>
                    <li>Reproduce it locally with the request above.</li>
                    <li>Deploy the fix, then click <strong>Mark as fixed</strong>. If it happens again, it reopens and you are alerted.</li>
                </ol>
            </x-admin.card>
        </div>
    </div>
@endsection
