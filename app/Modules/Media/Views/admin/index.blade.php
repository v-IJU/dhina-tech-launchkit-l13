@extends('layouts.admin')

@php $icons = ['image' => 'bi-image', 'document' => 'bi-file-earmark-text', 'video' => 'bi-camera-video', 'other' => 'bi-file-earmark']; @endphp

@section('content')
    <x-admin.page-header title="Media library" :breadcrumbs="['Content', 'Media library']">
        @can('media.create')
            <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="d-flex gap-2" id="uploadForm">
                @csrf
                <label class="btn btn-primary mb-0">
                    <i class="bi bi-cloud-upload me-1"></i> Upload files
                    <input type="file" name="files[]" multiple class="d-none" data-autosubmit accept=".{{ str_replace(',', ',.', \App\Modules\Media\Controllers\Admin\MediaController::MIMES) }}">
                </label>
            </form>
        @endcan
    </x-admin.page-header>

    @error('files.*')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @error('files')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <div class="row g-4">
        <div class="col-xl-9">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs-soft">
                        @foreach ([null => 'All files', 'image' => 'Images', 'document' => 'Documents', 'video' => 'Videos'] as $key => $label)
                            <li class="nav-item">
                                <a href="{{ route('admin.media.index', array_filter(['type' => $key ?: null, 'q' => request('q')])) }}" class="nav-link {{ $type === ($key ?: null) ? 'active' : '' }}">
                                    {{ $label }} <span class="badge badge-soft-secondary">{{ $key ? ($counts[$key] ?? 0) : $counts->sum() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <form method="GET" style="width: 240px">
                        @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                        <x-admin.search placeholder="Search files…" />
                    </form>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @forelse ($items as $item)
                            <div class="col-6 col-md-4 col-xxl-3">
                                <a href="{{ route('admin.media.index', array_filter(['selected' => $item->id, 'type' => $type, 'q' => request('q'), 'page' => request('page')])) }}"
                                   class="media-tile {{ $selected?->is($item) ? 'selected' : '' }}">
                                    <span class="media-thumb">
                                        @if ($item->type === 'image')
                                            <img src="{{ $item->thumbUrl() }}" alt="{{ $item->alt }}" loading="lazy">
                                        @else
                                            <i class="bi {{ $icons[$item->type] }}"></i>
                                        @endif
                                    </span>
                                    <span class="d-block p-2">
                                        <span class="d-block small fw-semibold text-truncate">{{ $item->title }}</span>
                                        <span class="d-block small text-secondary">{{ $item->file()?->human_readable_size }}</span>
                                    </span>
                                </a>
                            </div>
                        @empty
                            <div class="col-12"><x-admin.empty icon="bi-images" message="No files yet. Click “Upload files” to add images and documents." /></div>
                        @endforelse
                    </div>
                </div>
                @if ($items->hasPages())
                    <div class="card-footer">{{ $items->links() }}</div>
                @endif
            </div>
        </div>

        <div class="col-xl-3">
            <x-admin.card title="File details">
                @if ($selected)
                    @php $media = $selected->file(); @endphp
                    <div class="media-thumb rounded-3 mb-3" style="height: 160px">
                        @if ($selected->type === 'image')
                            <img src="{{ $selected->thumbUrl() }}" alt="{{ $selected->alt }}">
                        @else
                            <i class="bi {{ $icons[$selected->type] }}"></i>
                        @endif
                    </div>
                    <dl class="row small mb-3">
                        <dt class="col-5 text-secondary fw-normal">Type</dt><dd class="col-7">{{ $media?->mime_type }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Size</dt><dd class="col-7">{{ $media?->human_readable_size }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Uploaded</dt><dd class="col-7">{{ $selected->created_at->format('d M Y') }}</dd>
                        <dt class="col-5 text-secondary fw-normal">By</dt><dd class="col-7">{{ $selected->uploader?->name ?? '—' }}</dd>
                    </dl>
                    <label class="form-label" for="fileUrl">File URL</label>
                    <div class="input-group mb-3">
                        <input id="fileUrl" class="form-control form-control-sm code" value="{{ $selected->url() }}" readonly>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="{{ $selected->url() }}" aria-label="Copy URL"><i class="bi bi-clipboard"></i></button>
                    </div>
                    @can('media.edit')
                        <form method="POST" action="{{ route('admin.media.update', $selected) }}" class="d-flex flex-column gap-2">
                            @csrf @method('PUT')
                            <x-admin.form.input name="title" label="Title" :value="$selected->title" required />
                            <x-admin.form.input name="alt" label="Alt text" :value="$selected->alt" placeholder="Describe the image for screen readers" />
                            <button class="btn btn-primary btn-sm">Save details</button>
                        </form>
                    @endcan
                    <div class="d-flex gap-2 mt-3">
                        <a href="{{ $selected->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary flex-fill">Open</a>
                        @can('media.delete')
                            <x-admin.delete-button :action="route('admin.media.destroy', $selected)" confirm="Delete this file?" body="Pages and posts using it will show a broken image." label="Delete" button="Delete file" />
                        @endcan
                    </div>
                @else
                    <div class="text-secondary text-center py-4">Select a file to see its details.</div>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
