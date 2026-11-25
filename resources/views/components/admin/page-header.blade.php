@props(['title', 'breadcrumbs' => []])
{{--
    <x-admin.page-header title="Users" :breadcrumbs="['Access control', 'Users']">
        <a class="btn btn-primary" href="...">Add user</a>
    </x-admin.page-header>
--}}
@section('title', $title)
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-secondary text-decoration-none">Home</a></li>
                @foreach ($breadcrumbs as $label => $url)
                    @if (is_string($label))
                        <li class="breadcrumb-item"><a href="{{ $url }}" class="text-secondary text-decoration-none">{{ $label }}</a></li>
                    @else
                        <li class="breadcrumb-item text-secondary">{{ $url }}</li>
                    @endif
                @endforeach
            </ol>
        </nav>
        <h1>{{ $title }}</h1>
    </div>
    @if (trim($slot))
        <div class="d-flex flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
