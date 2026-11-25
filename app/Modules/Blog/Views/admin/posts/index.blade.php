@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Blog posts" :breadcrumbs="['Content', 'Blog posts']">
        @can('blog.create')
            <a href="{{ route('admin.blog.posts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add post</a>
        @endcan
    </x-admin.page-header>

    <x-admin.datatable :table="$table" search="Search posts…">
        @include('blog::admin.posts._table')
    </x-admin.datatable>
@endsection
