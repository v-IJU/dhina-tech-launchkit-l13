{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.check-all :table="$table" />
            <x-admin.datatable.th :table="$table" sort="title">Post</x-admin.datatable.th>
            <th>Author</th>
            <th>Category</th>
            <th>Tags</th>
            @if (\App\Core\Localization\Locales::translatable())<th>Languages</th>@endif
            <x-admin.datatable.th :table="$table" sort="status">Status</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="published_at">Date</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $post)
            <tr>
                <x-admin.datatable.check :table="$table" :value="$post->id" />
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <span class="rounded-2 flex-none overflow-hidden bg-body-tertiary d-flex align-items-center justify-content-center" style="width: 56px; height: 40px">
                            @if ($post->featured_image)<img src="{{ $post->featured_image }}" alt="" style="width: 100%; height: 100%; object-fit: cover">@else<i class="bi bi-image text-secondary"></i>@endif
                        </span>
                        <div class="d-flex flex-column">
                            <span class="fw-semibold">{{ $post->title }} @if ($post->is_featured)<i class="bi bi-star-fill text-warning small" title="Featured"></i>@endif</span>
                            <small class="text-secondary code">/blog/{{ $post->slug }}</small>
                        </div>
                    </div>
                </td>
                <td class="text-secondary">{{ $post->author?->name ?? '—' }}</td>
                <td>@if ($post->category)<span class="badge badge-soft-info">{{ $post->category->name }}</span>@endif</td>
                <td>@foreach (array_slice($post->tags ?? [], 0, 3) as $tag)<span class="badge badge-soft-secondary me-1">#{{ $tag }}</span>@endforeach</td>
                <x-admin.translation-links :record="$post" route="admin.blog.posts.translate" edit-route="admin.blog.posts.edit" />
                <td><x-admin.status-badge :status="$post->status" /></td>
                <td class="text-secondary text-nowrap">{{ ($post->published_at ?? $post->updated_at)->format('d M Y') }}</td>
                <td class="actions">
                    @if ($table->inTrash())
                        @can('blog.delete')
                            <x-admin.restore-button :action="route('admin.blog.posts.restore', $post)" />
                            <x-admin.delete-button :action="route('admin.blog.posts.force-delete', $post)" :confirm="'Delete “'.$post->title.'” forever?'" body="This cannot be undone." button="Delete forever" />
                        @endcan
                    @else
                    <a href="{{ route('blog.show', $post) }}" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="View on website" data-bs-toggle="tooltip" title="{{ $post->isLive() ? 'View' : 'Preview' }}"><i class="bi bi-eye"></i></a>
                    @can('blog.edit')
                        <a href="{{ route('admin.blog.posts.edit', $post) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Edit" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                    @endcan
                    @can('blog.delete')
                        <x-admin.delete-button :action="route('admin.blog.posts.destroy', $post)" :confirm="'Delete “'.$post->title.'”?'" body="It is removed from the website and moved to trash." button="Delete post" />
                    @endcan
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty :icon="$table->inTrash() ? 'bi-trash3' : 'bi-journal-text'" :message="$table->emptyMessage('posts')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="posts" />
