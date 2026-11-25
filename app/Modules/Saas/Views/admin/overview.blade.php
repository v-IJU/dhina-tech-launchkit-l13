@extends('layouts.admin')

@php
    $chart = ['labels' => $revenue->pluck('label'), 'series' => [['label' => 'Revenue ('.$currency.')', 'values' => $revenue->map(fn ($r) => round($r['total'] / 100, 2)), 'color' => '#2563eb']]];
@endphp

@section('content')
    <x-admin.page-header title="SaaS overview" :breadcrumbs="['SaaS', 'Overview']">
        @can('saas.create')
            <a href="{{ route('admin.saas.tenants.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Onboard tenant</a>
        @endcan
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        @foreach ($stats as $i => $stat)
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <span class="stat-icon" style="background: {{ ['#2563eb', '#16a34a', '#7c3aed', '#ea580c'][$i] }}"><i class="bi {{ $stat['icon'] }}"></i></span>
                    <div class="d-flex flex-column min-w-0">
                        <span class="text-secondary small fw-medium">{{ $stat['label'] }}</span>
                        <span class="stat-value">{{ $stat['value'] }}</span>
                        <span class="small text-secondary">{{ $stat['hint'] }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <x-admin.card title="Revenue, last 6 months" subtitle="Paid invoices, including tax" class="h-100">
                <div style="height: 260px">
                    <canvas data-chart="line" role="img" aria-label="Revenue chart"
                            data-values="{{ json_encode($chart) }}"></canvas>
                </div>
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            <x-admin.card title="Tenants by status" class="h-100">
                <ul class="list-unstyled mb-0">
                    @foreach (\App\Modules\Saas\Models\Tenant::STATUSES as $key => $label)
                        <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <a href="{{ route('admin.saas.tenants.index', ['status' => $key]) }}" class="text-body">@include('saas::admin._status', ['status' => $key])</a>
                            <span class="fw-semibold">{{ $byStatus[$key] ?? 0 }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>
        </div>
    </div>

    <div class="row g-3">
        @foreach ([['Trials ending in 7 days', $trialsEnding, 'trial'], ['Need attention', $problems, 'problem'], ['Newest tenants', $recent, 'recent']] as [$title, $rows, $kind])
            <div class="col-xl-4">
                <x-admin.card :title="$title" flush class="h-100">
                    @forelse ($rows as $tenant)
                        <a href="{{ route('admin.saas.tenants.show', $tenant) }}" class="d-flex align-items-center gap-3 px-4 py-3 border-top text-body">
                            <span class="flex-fill min-w-0">
                                <span class="fw-semibold d-block text-truncate">{{ $tenant->name }}</span>
                                <small class="text-secondary">{{ $tenant->plan?->name ?? 'No plan' }}</small>
                            </span>
                            @if ($kind === 'trial')
                                <small class="text-secondary text-nowrap">{{ $tenant->trial_ends_at?->diffForHumans() }}</small>
                            @elseif ($kind === 'problem')
                                @include('saas::admin._status', ['status' => $tenant->status])
                            @else
                                <small class="text-secondary text-nowrap">{{ $tenant->created_at?->diffForHumans() }}</small>
                            @endif
                        </a>
                    @empty
                        <div class="px-4 py-3 border-top small text-secondary">Nothing here.</div>
                    @endforelse
                </x-admin.card>
            </div>
        @endforeach
    </div>
@endsection
