{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.th :table="$table" sort="title">Page</x-admin.datatable.th>
            <th>URL</th>
            <th>Blocks</th>
            @if (\App\Core\Localization\Locales::translatable())<th>Languages</th>@endif
            <x-admin.datatable.th :table="$table" sort="status">Status</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="updated_at">Updated</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $page)
            <tr>
                <td class="fw-semibold">{{ $page->title }} @if ($page->is_home)<span class="badge badge-soft-primary ms-1">Home page</span>@endif</td>
                <td><a href="{{ $page->url() }}" target="_blank" rel="noopener" class="code text-secondary">{{ $page->is_home ? '/' : '/'.$page->slug }}</a></td>
                <td>
                    <span class="badge badge-soft-secondary">{{ count($page->blocks ?? []) }} blocks</span>
                    <small class="text-secondary d-none d-xl-inline">{{ collect($page->blocks)->pluck('type')->map(fn ($t) => \App\Modules\Pages\Support\BlockRegistry::get($t)['label'] ?? $t)->take(4)->join(', ') }}</small>
                </td>
                <x-admin.translation-links :record="$page" route="admin.pages.translate" edit-route="admin.pages.edit" />
                <td><x-admin.status-badge :status="$page->status" /></td>
                <td class="text-secondary text-nowrap">{{ $page->updated_at->diffForHumans() }} @if ($page->editor)<small>by {{ $page->editor->name }}</small>@endif</td>
                <td class="actions">
                    @can('pages.create')
                        <form method="POST" action="{{ route('admin.pages.duplicate', $page) }}" class="d-inline">@csrf<button class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Duplicate" data-bs-toggle="tooltip" title="Duplicate"><i class="bi bi-copy"></i></button></form>
                    @endcan
                    @can('pages.edit')
                        <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-primary">Open builder</a>
                    @endcan
                    @can('pages.delete')
                        @unless ($page->is_home)
                            <x-admin.delete-button :action="route('admin.pages.destroy', $page)" :confirm="'Delete page '.$page->title.'?'" body="Links to it will show a 404 page." />
                        @endunless
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty icon="bi-window" :message="$table->isFiltered() ? 'No pages match these filters.' : 'No pages yet.'" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="pages" />
