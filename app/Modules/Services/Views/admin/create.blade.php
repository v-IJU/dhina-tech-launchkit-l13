@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Add service" :breadcrumbs="['Services' => route('admin.services.index'), 'Add']">
        <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.services.store') }}" enctype="multipart/form-data">
        @csrf
        @include('services::admin._form')
    </form>
@endsection
