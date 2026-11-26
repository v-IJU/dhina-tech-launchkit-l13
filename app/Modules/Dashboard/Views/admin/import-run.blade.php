@extends('layouts.admin')

@section('title', 'Import #'.$run->id)

@section('content')
    <x-admin.page-header :title="\Illuminate\Support\Str::headline($run->module).' import #'.$run->id" :breadcrumbs="[\Illuminate\Support\Str::headline($run->module), 'Import']" />

    <x-admin.import-history :runs="collect([$run])" />

    @if ($skipped->isNotEmpty())
        <x-admin.card :title="number_format($run->failed_rows).' row(s) skipped'" :flush="true" class="mt-4">
            <x-slot:actions>
                <a href="{{ route('admin.imports.errors', $run) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i> Download skipped rows</a>
            </x-slot:actions>
            <div class="table-responsive" style="max-height: 480px">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th style="width: 80px">Row</th><th>Problem</th><th>Values</th></tr></thead>
                    <tbody>
                    @foreach ($skipped as $error)
                        <tr>
                            <td class="text-secondary">{{ $error->row }}</td>
                            <td class="text-danger">{!! collect($error->messages)->map(fn ($m) => e($m))->implode('<br>') !!}</td>
                            <td class="small text-secondary">{{ \Illuminate\Support\Str::limit(collect($error->values)->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · '), 160) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($run->failed_rows > 200)
                <div class="card-footer small text-secondary">Showing the first 200. Download the file for all of them.</div>
            @endif
        </x-admin.card>
    @endif
@endsection
