@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Edit FAQ" :breadcrumbs="['FAQs' => route('admin.faqs.index'), 'Edit']">
        <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.faqs.update', $faq) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('faqs::admin._form')
    </form>
@endsection
