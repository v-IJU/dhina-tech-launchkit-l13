@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Edit testimonial" :breadcrumbs="['Testimonials' => route('admin.testimonials.index'), 'Edit']">
        <a href="{{ route('admin.testimonials.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.testimonials.update', $testimonial) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('testimonials::admin._form')
    </form>
@endsection
