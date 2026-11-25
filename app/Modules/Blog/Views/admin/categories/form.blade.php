@extends('layouts.admin')

@section('content')
    <x-admin.page-header :title="$category->exists ? 'Edit category' : 'Add category'" :breadcrumbs="['Content', 'Categories' => route('admin.blog.categories.index'), $category->exists ? 'Edit' : 'Add']" />

    <form method="POST" action="{{ $category->exists ? route('admin.blog.categories.update', $category) : route('admin.blog.categories.store') }}" style="max-width: 720px">
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <x-admin.card title="Category details">
            <div class="d-flex flex-column gap-3">
                <x-admin.form.input name="name" id="cat_name" label="Name" :value="$category->name" required />
                <x-admin.form.input name="slug" label="URL" prefix="/blog/category/" :value="$category->slug" data-slug-from="#cat_name" hint="Leave empty to make it from the name." />
                <x-admin.form.textarea name="description" label="Description" :value="$category->description" rows="2" />
                <x-admin.form.checkbox name="is_active" label="Show on the website" :checked="$category->is_active" />
            </div>
            <x-slot:footer>
                <a href="{{ route('admin.blog.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary">{{ $category->exists ? 'Save changes' : 'Create category' }}</button>
            </x-slot:footer>
        </x-admin.card>
    </form>
@endsection
