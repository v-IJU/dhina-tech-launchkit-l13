{{-- One menu row with an inline edit form. Used for every menu location. --}}
<div class="list-group-item py-2" data-id="{{ $item->id }}" @style(['opacity: .55' => ! $item->is_active])>
    <div class="d-flex align-items-center gap-2">
        @can('menus.edit')<i class="bi bi-grip-vertical drag-handle" title="Drag to reorder"></i>@endcan
        @if ($item->icon)<i class="bi {{ $item->icon }} text-secondary"></i>@endif
        <div class="flex-fill min-w-0">
            <div class="fw-semibold">{{ $item->label }}</div>
            <small class="text-secondary code text-truncate d-block">
                {{ $item->route ?: $item->url }}
                @if ($item->permission) · {{ $item->permission }} @endif
                @if ($item->children->isNotEmpty()) · {{ $item->children->count() }} sub-items @endif
            </small>
        </div>
        @can('menus.edit')
            <button class="btn btn-sm btn-icon btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-{{ $item->id }}" aria-label="Edit {{ $item->label }}"><i class="bi bi-pencil"></i></button>
            <form method="POST" action="{{ route('admin.menus.toggle', $item) }}">
                @csrf @method('PATCH')
                <button class="badge border-0 {{ $item->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $item->is_active ? 'Visible' : 'Hidden' }}</button>
            </form>
        @endcan
        @if ($item->location !== 'admin')
            @can('menus.delete')
                <x-admin.delete-button :action="route('admin.menus.destroy', $item)" :confirm="'Remove '.$item->label.'?'" body="Its sub-items are removed too." button="Remove" />
            @endcan
        @endif
    </div>

    @can('menus.edit')
        <form method="POST" action="{{ route('admin.menus.update', $item) }}" class="collapse mt-2" id="edit-{{ $item->id }}">
            @csrf @method('PUT')
            <div class="row g-2 align-items-end">
                <x-admin.form.input class="col-md-4" name="label" label="Label" :value="$item->label" :id="'label-'.$item->id" />
                @if ($item->location === 'admin')
                    <x-admin.form.input class="col-md-4" name="icon" label="Icon" :value="$item->icon" :id="'icon-'.$item->id" hint='<a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Bootstrap icons</a>' />
                @else
                    <x-admin.form.input class="col-md-4" name="url" label="Link" :value="$item->url" :id="'url-'.$item->id" />
                    <div class="col-md-2"><x-admin.form.checkbox name="new_tab" label="New tab" :checked="$item->new_tab" :id="'tab-'.$item->id" /></div>
                    @foreach (\App\Core\Localization\Locales::translatable() as $locale)
                        <x-admin.form.input class="col-md-4" :name="'translations['.$locale.']'" :label="'Label in '.\App\Core\Localization\Locales::name($locale)" :value="$item->translations[$locale] ?? ''" :id="'label-'.$locale.'-'.$item->id" :placeholder="$item->label" />
                    @endforeach
                @endif
                <div class="col-md-2"><button class="btn btn-primary w-100">Save</button></div>
            </div>
        </form>
    @endcan

    @if ($item->children->isNotEmpty())
        <div class="list-group mt-2 ms-4" data-menu-sortable="{{ route('admin.menus.sort') }}">
            @foreach ($item->children as $child)
                @include('menus::admin._item', ['item' => $child])
            @endforeach
        </div>
    @endif
</div>
