@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Menu builder" :breadcrumbs="['Appearance', 'Menu builder']" />

    <ul class="nav nav-tabs-soft mb-4">
        @foreach ($locations as $key => $label)
            <li class="nav-item"><a href="{{ route('admin.menus.index', ['location' => $key]) }}" class="nav-link {{ $location === $key ? 'active' : '' }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    @if ($location === 'admin')
        <p class="text-secondary">Drag <i class="bi bi-grip-vertical"></i> to reorder within a group. Items come from each module's <code>module.json</code>; a role only sees items it has permission to view.</p>
        <div class="row g-3">
            @foreach ($groups as $group => $groupItems)
                <div class="col-lg-6">
                    <x-admin.card :title="$group" :flush="true">
                        <x-slot:actions><span class="badge badge-soft-secondary">{{ $groupItems->count() }} items</span></x-slot:actions>
                        <div class="list-group list-group-flush" data-menu-sortable="{{ route('admin.menus.sort') }}">
                            @foreach ($groupItems as $item)
                                @include('menus::admin._item', ['item' => $item])
                            @endforeach
                        </div>
                    </x-admin.card>
                </div>
            @endforeach
        </div>
    @else
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card :title="$locations[$location]" subtitle="Rendered by the Tailwind website layout. Drag to reorder." :flush="true">
                    <div class="list-group list-group-flush" data-menu-sortable="{{ route('admin.menus.sort') }}">
                        @forelse ($items as $item)
                            @include('menus::admin._item', ['item' => $item])
                        @empty
                            <x-admin.empty icon="bi-list" message="No menu items yet." />
                        @endforelse
                    </div>
                </x-admin.card>
            </div>
            <div class="col-xl-4">
                @can('menus.create')
                    <form method="POST" action="{{ route('admin.menus.store') }}">
                        @csrf
                        <input type="hidden" name="location" value="{{ $location }}">
                        <x-admin.card title="Add menu item">
                            <div class="d-flex flex-column gap-3">
                                <x-admin.form.input name="label" label="Label" placeholder="Careers" required />
                                <x-admin.form.input name="url" label="Link" placeholder="/careers or https://…" required />
                                <x-admin.form.select name="parent_id" label="Show under" :options="$items->pluck('label', 'id')->all()" placeholder="Top level" />
                                <x-admin.form.checkbox name="new_tab" label="Open in a new tab" />
                            </div>
                            <x-slot:footer><button class="btn btn-primary">Add to menu</button></x-slot:footer>
                        </x-admin.card>
                    </form>
                @endcan
            </div>
        </div>
    @endif
@endsection
