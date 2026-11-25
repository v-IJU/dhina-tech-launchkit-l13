@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Edit service" :breadcrumbs="['Services' => route('admin.services.index'), 'Edit']">
        <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.services.update', $service) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('services::admin._form')
    </form>
@endsection
