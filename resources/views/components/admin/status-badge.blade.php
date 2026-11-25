@props(['status'])
@php
    $map = [
        'active' => 'success', 'published' => 'success', 'enabled' => 'success', 'yes' => 'success',
        'inactive' => 'warning', 'draft' => 'warning', 'pending' => 'warning',
        'suspended' => 'danger', 'blocked' => 'danger', 'disabled' => 'secondary', 'no' => 'secondary',
        'scheduled' => 'info',
    ];
    $key = is_bool($status) ? ($status ? 'active' : 'inactive') : strtolower((string) $status);
    $label = is_bool($status) ? ($status ? 'Active' : 'Inactive') : \Illuminate\Support\Str::headline((string) $status);
@endphp
<span {{ $attributes->class(['badge', 'badge-soft-'.($map[$key] ?? 'secondary')]) }}>{{ $label }}</span>
