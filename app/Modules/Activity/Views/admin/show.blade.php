@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Activity details" :breadcrumbs="['System', 'Activity log' => route('admin.activity.index'), '#'.$activity->id]">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    @php
        $props = $activity->properties;
        $new = $props['attributes'] ?? [];
        $old = $props['old'] ?? [];
        $extra = collect($props)->except(['attributes', 'old']);
    @endphp

    <div class="row g-4">
        <div class="col-lg-4">
            <x-admin.card title="Summary">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-secondary">When</dt><dd class="col-7">{{ $activity->created_at->format('d M Y, H:i:s') }}</dd>
                    <dt class="col-5 text-secondary">Who</dt><dd class="col-7">{{ $activity->causer?->name ?? 'System' }}</dd>
                    <dt class="col-5 text-secondary">Event</dt><dd class="col-7">{{ $activity->event ?: '—' }}</dd>
                    <dt class="col-5 text-secondary">Area</dt><dd class="col-7"><code>{{ $activity->log_name }}</code></dd>
                    <dt class="col-5 text-secondary">Record</dt><dd class="col-7">{{ $activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : '—' }}</dd>
                    <dt class="col-5 text-secondary">Description</dt><dd class="col-7">{{ $activity->description }}</dd>
                </dl>
            </x-admin.card>
        </div>
        <div class="col-lg-8">
            <x-admin.card title="Changes" :flush="true">
                @if ($new || $old)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                            <tbody>
                            @foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field)
                                <tr>
                                    <td class="fw-semibold">{{ $field }}</td>
                                    <td class="text-danger-emphasis">{{ is_scalar($old[$field] ?? null) ? \Illuminate\Support\Str::limit((string) $old[$field], 120) : json_encode($old[$field] ?? null) }}</td>
                                    <td class="text-success-emphasis">{{ is_scalar($new[$field] ?? null) ? \Illuminate\Support\Str::limit((string) $new[$field], 120) : json_encode($new[$field] ?? null) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-secondary">No field changes recorded for this entry.</div>
                @endif
                @if ($extra->isNotEmpty())
                    <pre class="m-3 p-3 bg-body-tertiary rounded small">{{ json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
