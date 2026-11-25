@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Add testimonial" :breadcrumbs="['Testimonials' => route('admin.testimonials.index'), 'Add']">
        <a href="{{ route('admin.testimonials.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.testimonials.store') }}" enctype="multipart/form-data">
        @csrf
        @include('testimonials::admin._form')
    </form>
@endsection
