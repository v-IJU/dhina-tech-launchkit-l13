@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Dashboard" :breadcrumbs="['Main']">
        <a href="{{ request()->fullUrl() }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</a>
    </x-admin.page-header>

    {{-- Key numbers --}}
    <div class="row g-3 mb-4">
        @foreach ($stats as $stat)
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <span class="stat-icon" style="background: {{ $stat['color'] }}"><i class="bi {{ $stat['icon'] }}"></i></span>
                    <div class="d-flex flex-column min-w-0">
                        <span class="text-secondary small fw-medium">{{ $stat['label'] }}</span>
                        <span class="stat-value">{{ number_format($stat['value']) }}</span>
                        <span><span class="badge badge-soft-{{ $stat['tone'] }}">{{ $stat['note'] }}</span></span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <x-admin.card :title="'Activity over the last '.$range.' days'" subtitle="Counted from your database" class="h-100">
                <x-slot:actions>
                    <div class="nav nav-tabs-soft">
                        @foreach ([7, 14, 30] as $r)
                            <a href="{{ route('admin.dashboard', ['range' => $r]) }}" class="nav-link py-1 {{ $range === $r ? 'active' : '' }}">{{ $r }}D</a>
                        @endforeach
                    </div>
                </x-slot:actions>
                <div class="d-flex gap-4 mb-3">
                    @foreach ($chart['series'] as $series)
                        <div><div class="small text-secondary">{{ $series['label'] }}</div><div class="fs-5 fw-bold">{{ number_format(array_sum($series['values'])) }}</div></div>
                    @endforeach
                </div>
                <div style="height: 260px">
                    <canvas data-chart="line" data-values='@json($chart)' role="img" aria-label="Daily activity chart"></canvas>
                </div>
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            <x-admin.card title="Users by role" subtitle="Live from the users table" class="h-100">
                <div class="position-relative mx-auto" style="width: 190px; height: 190px">
                    <canvas data-chart="doughnut" data-values='@json($roles)' role="img" aria-label="Users by role chart"></canvas>
                    <div class="position-absolute top-50 start-50 translate-middle text-center">
                        <div class="fs-3 fw-bold lh-1">{{ $roles['total'] }}</div><small class="text-secondary">users</small>
                    </div>
                </div>
                <div class="d-flex flex-column gap-2 mt-4">
                    @foreach ($roles['labels'] as $i => $label)
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-1" style="width: 10px; height: 10px; background: {{ $roles['colors'][$i] }}"></span>
                            <span class="flex-fill">{{ $label }}</span>
                            <strong>{{ $roles['values'][$i] }}</strong>
                            <span class="text-secondary text-end" style="width: 44px">{{ $roles['total'] ? round($roles['values'][$i] / $roles['total'] * 100) : 0 }}%</span>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <x-admin.card title="Recently added users" :flush="true" class="h-100">
                @can('users.view')
                    <x-slot:actions><a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">View all</a></x-slot:actions>
                @endcan
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
                        <tbody>
                        @foreach ($recentUsers as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <x-admin.avatar :user="$user" size="sm" />
                                        <div class="d-flex flex-column"><span class="fw-semibold">{{ $user->name }}</span><small class="text-secondary">{{ $user->email }}</small></div>
                                    </div>
                                </td>
                                <td>@foreach ($user->roles as $role)<span class="badge badge-soft-secondary"><span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background: {{ $role->color }}"></span>{{ $role->display_name ?: $role->name }}</span>@endforeach</td>
                                <td><x-admin.status-badge :status="$user->status" /></td>
                                <td class="text-secondary">{{ $user->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            @if ($planUsage !== null)
                @php $saasTenant = \App\Core\Tenancy\Tenancy::tenant(); @endphp
                <x-admin.card title="Your plan" :subtitle="($saasTenant->plan?->name ?? 'No plan').' · '.(\App\Modules\Saas\Models\Tenant::STATUSES[$saasTenant->status] ?? $saasTenant->status)" class="h-100">
                    @if ($saasTenant->status === 'trial')
                        <div class="alert alert-info py-2 small">Free trial: {{ $saasTenant->trialDaysLeft() }} {{ \Illuminate\Support\Str::plural('day', $saasTenant->trialDaysLeft()) }} left.</div>
                    @endif
                    <div class="d-flex flex-column gap-3">
                        @foreach ($planUsage as $key => [$used, $limit])
                            <div>
                                <div class="d-flex small"><span class="flex-fill">{{ \App\Modules\Saas\Models\Plan::LIMITS[$key] }}</span><span class="fw-semibold">{{ number_format($used) }} / {{ $limit === null ? '∞' : number_format($limit) }}</span></div>
                                <div class="progress mt-1" style="height: 6px"><div class="progress-bar {{ $limit && $used >= $limit ? 'bg-danger' : '' }}" style="width: {{ $limit ? min(100, $used / $limit * 100) : 4 }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                    @can('billing.view')
                        <a href="{{ route('admin.billing.index') }}" class="btn btn-sm btn-outline-primary mt-3">Billing and invoices</a>
                    @endcan
                </x-admin.card>
            @else
            <x-admin.card title="System status" class="h-100">
                <div class="d-flex flex-column gap-3">
                    @foreach ($services as $service)
                        <div class="d-flex align-items-center gap-3">
                            <span class="status-dot {{ $service['ok'] ? 'bg-success' : 'bg-warning' }}"></span>
                            <div class="flex-fill d-flex flex-column">
                                @if (! empty($service['url']))
                                    <a href="{{ $service['url'] }}" class="fw-semibold text-body">{{ $service['name'] }}</a>
                                @else
                                    <span class="fw-semibold">{{ $service['name'] }}</span>
                                @endif
                                <small class="text-secondary">{{ $service['detail'] }}</small>
                            </div>
                            <code class="text-secondary">{{ $service['value'] }}</code>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6 col-xl-4">
            <x-admin.card title="Recent activity" class="h-100">
                @can('activity.view')
                    <x-slot:actions><a href="{{ route('admin.activity.index') }}" class="btn btn-sm btn-outline-secondary">View log</a></x-slot:actions>
                @endcan
                <div class="d-flex flex-column gap-3">
                    @forelse ($activity as $item)
                        <div class="d-flex gap-3">
                            <span class="timeline-dot {{ ['created' => 'bg-success', 'deleted' => 'bg-danger', 'login' => 'bg-info'][$item->event] ?? 'bg-primary' }}"></span>
                            <div class="d-flex flex-column">
                                <span>{{ $item->description }} @if ($item->subject_type)<span class="text-secondary">· {{ class_basename($item->subject_type) }}</span>@endif</span>
                                <small class="text-secondary">{{ $item->causer?->name ?? 'System' }} · {{ $item->created_at->diffForHumans() }}</small>
                            </div>
                        </div>
                    @empty
                        <span class="text-secondary">No activity yet.</span>
                    @endforelse
                </div>
            </x-admin.card>
        </div>
        @if ($latestMessages->isNotEmpty() || module_enabled('inbox'))
            <div class="col-lg-6 col-xl-4">
                <x-admin.card title="Latest enquiries" :flush="true" class="h-100">
                    @can('inbox.view')
                        <x-slot:actions><a href="{{ route('admin.inbox.index') }}" class="btn btn-sm btn-outline-secondary">Open inbox</a></x-slot:actions>
                    @endcan
                    <div class="list-group list-group-flush">
                        @forelse ($latestMessages as $message)
                            <a href="{{ auth()->user()->can('inbox.view') ? route('admin.inbox.show', $message) : '#' }}" class="list-group-item list-group-item-action d-flex gap-3 py-3">
                                <x-admin.avatar :name="$message->name" size="sm" />
                                <div class="min-w-0 flex-fill">
                                    <div class="d-flex justify-content-between gap-2"><span @class(['fw-bold' => ! $message->read_at])>{{ $message->name }}</span><small class="text-secondary text-nowrap">{{ $message->created_at->diffForHumans(short: true) }}</small></div>
                                    <div class="small text-secondary text-truncate">{{ $message->subject ?: \Illuminate\Support\Str::limit($message->message, 60) }}</div>
                                </div>
                            </a>
                        @empty
                            <div class="p-4 text-secondary">No enquiries yet.</div>
                        @endforelse
                    </div>
                </x-admin.card>
            </div>
        @endif
        <div class="col-lg-6 col-xl-4">
            <x-admin.card title="Quick actions" class="h-100">
                <div class="row g-2">
                    @foreach ($quickActions as $action)
                        <div class="col-6">
                            <a href="{{ $action['url'] }}" class="btn btn-outline-secondary w-100 d-flex flex-column align-items-center gap-2 py-3">
                                <span class="d-flex align-items-center justify-content-center rounded-3 bg-{{ $action['tone'] }}-subtle text-{{ $action['tone'] }}-emphasis" style="width: 36px; height: 36px"><i class="bi {{ $action['icon'] }}"></i></span>
                                <span class="small">{{ $action['label'] }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
