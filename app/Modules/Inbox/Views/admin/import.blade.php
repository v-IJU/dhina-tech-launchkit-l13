@extends('layouts.admin')

@section('title', 'Import Subscribers')

@section('content')
    <x-admin.page-header title="Import Subscribers" :breadcrumbs="['Website CMS', 'Subscribers', 'Import']">
        <a href="{{ route('admin.subscribers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to list</a>
    </x-admin.page-header>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <x-admin.card title="Upload a file">
                <form method="POST" action="{{ route('admin.subscribers.import.store') }}" enctype="multipart/form-data" class="d-flex flex-column gap-3">
                    @csrf
                    <div>
                        <label for="import_file" class="form-label">Excel or CSV file</label>
                        <input type="file" name="file" id="import_file" accept=".xlsx,.xls,.csv" class="form-control @error('file') is-invalid @enderror" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Up to {{ setting('import_max_mb', 20) }} MB. More than {{ number_format((int) setting('import_queue_above', 1000)) }} rows run in the background; you get a notification when it's done.</div>
                    </div>
                    <x-admin.form.checkbox name="dry_run" label="Only check the file (don't save anything)" :checked="old('dry_run', false)" />
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary"><i class="bi bi-upload me-1"></i> Import</button>
                        <a href="{{ route('admin.subscribers.import.template') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i> Download template</a>
                    </div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-lg-7">
            <x-admin.card title="Columns" :flush="true">
                <div class="px-4 pt-3 small text-secondary">
                    <ul class="mb-3 ps-3">
                        <li>First row = headings, any order. Unknown columns are ignored.</li>
                        <li>New emails are <strong>added</strong>; emails already on the list are counted as <strong>updated</strong> (nothing changes). Duplicates and invalid emails are skipped.</li><li>Built for big lists: 50,000 emails import in seconds (chunk import, two queries per chunk).</li>
                        <li>Tip: <strong>Export</strong> the list, edit it in Excel, import it back.</li>
                        <li>Rows with errors are skipped; download them from "Recent imports", fix and import again.</li>
                    </ul>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Heading</th><th>Required</th><th>Rules</th><th>Example</th></tr></thead>
                        <tbody>
                        @foreach ($columns as $column)
                            <tr>
                                <td class="fw-semibold">{{ $column['heading'] }}</td>
                                <td>@if ($column['required'])<span class="badge badge-soft-danger">Required</span>@else<span class="text-secondary small">Optional</span>@endif</td>
                                <td class="small text-secondary">{{ $column['rules'] }}</td>
                                <td class="small">{{ $column['example'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>

    <x-admin.import-history :runs="$runs" />
@endsection
