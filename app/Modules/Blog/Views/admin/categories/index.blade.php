@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Blog categories" :breadcrumbs="['Content', 'Blog' => route('admin.blog.posts.index'), 'Categories']">
        @can('categories.create')
            <a href="{{ route('admin.blog.categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add category</a>
        @endcan
    </x-admin.page-header>

    <x-admin.card :flush="true">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Name</th><th>URL</th><th>Description</th><th>Posts</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td class="fw-semibold">{{ $category->name }}</td>
                        <td><code class="text-secondary">/blog/category/{{ $category->slug }}</code></td>
                        <td class="text-secondary">{{ $category->description }}</td>
                        <td>{{ $category->posts_count }}</td>
                        <td><x-admin.status-badge :status="$category->is_active" /></td>
                        <td class="actions">
                            @can('categories.edit')
                                <a href="{{ route('admin.blog.categories.edit', $category) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                            @endcan
                            @can('categories.delete')
                                <x-admin.delete-button :action="route('admin.blog.categories.destroy', $category)" :confirm="'Delete category '.$category->name.'?'" body="Its posts stay published, without a category." />
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-admin.empty icon="bi-tags" message="No categories yet." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection
