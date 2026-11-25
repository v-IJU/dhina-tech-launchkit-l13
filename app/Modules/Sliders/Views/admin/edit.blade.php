@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Edit slider" :breadcrumbs="['Sliders' => route('admin.sliders.index'), 'Edit']">
        <a href="{{ route('admin.sliders.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.sliders.update', $slider) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('sliders::admin._form')
    </form>
@endsection
