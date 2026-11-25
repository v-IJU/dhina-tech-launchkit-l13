{{-- Shared "Import CSV" page used by every controller with the HandlesImport trait. --}}
@extends('layouts.admin')

@section('title', 'Import '.$title)

@section('content')
    <x-admin.page-header :title="'Import '.$title" :breadcrumbs="$breadcrumbs ?? [$title, 'Import']">
        <a href="{{ $back }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to list</a>
    </x-admin.page-header>

    @if ($result)
        <div class="alert {{ $result->fatal ? 'alert-danger' : ($result->ok() ? 'alert-success' : 'alert-warning') }} d-flex gap-3 align-items-start">
            <i class="bi {{ $result->fatal ? 'bi-x-octagon' : ($result->ok() ? 'bi-check-circle' : 'bi-exclamation-triangle') }} fs-4"></i>
            <div>
                <div class="fw-semibold">
                    @if ($result->fatal)
                        The file could not be imported.
                    @elseif ($result->dryRun)
                        Check finished. Nothing was saved.
                    @else
                        Import finished.
                    @endif
                </div>
                <div>{{ $result->summary() }}</div>
                @if ($result->ignoredColumns)
                    <div class="small text-secondary mt-1">Ignored columns: {{ implode(', ', $result->ignoredColumns) }}</div>
                @endif
                @if ($result->dryRun && ! $result->fatal && $result->ok())
                    <div class="small mt-1">Everything looks good. Untick "Only check" and upload again to save.</div>
                @endif
            </div>
        </div>

        @if ($result->errors)
            <x-admin.card :title="$result->failed().' row(s) with errors'" :flush="true" class="mb-4">
                <div class="table-responsive" style="max-height: 420px">
                    <table class="table table-sm align-middle">
                        <thead><tr><th style="width: 90px">Row</th><th>Problem</th><th>Values</th></tr></thead>
                        <tbody>
                        @foreach (array_slice($result->errors, 0, 200) as $error)
                            <tr>
                                <td class="text-secondary">Line {{ $error['line'] }}</td>
                                <td class="text-danger">{!! collect($error['messages'])->map(fn ($m) => e($m))->implode('<br>') !!}</td>
                                <td class="small text-secondary">{{ \Illuminate\Support\Str::limit(collect($error['values'])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · '), 140) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($result->failed() > 200)
                    <div class="card-footer small text-secondary">Showing the first 200 of {{ $result->failed() }} errors.</div>
                @endif
            </x-admin.card>
        @endif
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <x-admin.card title="Upload a file">
                <form method="POST" action="{{ route('admin.'.$slug.'.import.store') }}" enctype="multipart/form-data" class="d-flex flex-column gap-3">
                    @csrf
                    <div>
                        <label for="import_file" class="form-label">CSV file</label>
                        <input type="file" name="file" id="import_file" accept=".csv,text/csv" class="form-control @error('file') is-invalid @enderror" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Up to {{ number_format(config('starter.import_max_rows', 5000)) }} rows. In Excel: File → Save as → <strong>CSV UTF-8</strong>.</div>
                    </div>
                    <x-admin.form.checkbox name="dry_run" label="Only check the file (don't save anything)" :checked="old('dry_run', true)" />
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary"><i class="bi bi-upload me-1"></i> Upload</button>
                        <a href="{{ route('admin.'.$slug.'.import.template') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Download template</a>
                    </div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-lg-7">
            <x-admin.card title="How it works" :flush="true">
                <div class="px-4 pt-3 small text-secondary">
                    <ul class="mb-3 ps-3">
                        <li>The first row must be the column headings. Order doesn't matter; unknown columns are ignored.</li>
                        <li>A row with an <strong>ID</strong> (or other key) that already exists <strong>updates</strong> that record; other rows are <strong>added</strong>.</li>
                        <li>Tip: use <strong>Export CSV</strong> on the list, edit the file, and import it back.</li>
                        <li>Rows with errors are skipped and listed; all other rows are saved.</li>
                        @isset($help)<li>{!! $help !!}</li>@endisset
                    </ul>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Column heading</th><th>Required</th><th>Rules</th></tr></thead>
                        <tbody>
                        @foreach ($columns as $column)
                            <tr>
                                <td class="fw-semibold">{{ $column['label'] }}</td>
                                <td>@if ($column['required'])<span class="badge badge-soft-danger">Required</span>@else<span class="text-secondary small">Optional</span>@endif</td>
                                <td class="small text-secondary code">{{ $column['rules'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
