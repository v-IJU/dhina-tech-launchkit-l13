@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Add FAQ" :breadcrumbs="['FAQs' => route('admin.faqs.index'), 'Add']">
        <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.faqs.store') }}" enctype="multipart/form-data">
        @csrf
        @include('faqs::admin._form')
    </form>
@endsection
