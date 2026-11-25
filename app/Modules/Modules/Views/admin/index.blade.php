@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Modules" :breadcrumbs="['System', 'Modules']">
        @can('modules.edit')
            <form method="POST" action="{{ route('admin.modules.sync') }}">@csrf<button class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i> Sync permissions &amp; menus</button></form>
        @endcan
    </x-admin.page-header>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <form method="POST" action="{{ route('admin.modules.generate') }}">
                @csrf
                <x-admin.card title="Module generator" subtitle="Same result as running php artisan make:module">
                    @unless ($canGenerate)
                        <div class="alert alert-warning small">The generator writes code files, so it only runs when <code>APP_ENV=local</code>. On this server, copy the command below and run it on your machine instead.</div>
                    @endunless
                    <div class="row g-3">
                        <x-admin.form.input class="col-md-6" name="name" label="Module name (singular)" value="Product" required hint="e.g. Product, Testimonial, CaseStudy" />
                        <x-admin.form.select class="col-md-6" name="group" label="Sidebar group" :options="array_combine($groups, $groups)" value="Modules" />
                        <x-admin.form.input class="col-12" name="fields" label="Fields" value="name:string,sku:string:unique,price:decimal,stock:integer,photo:image,description:editor,is_active:boolean" required
                            :hint="'Format <code>name:type[:nullable][:unique]</code>. Types: '.implode(', ', $types)" />
                        <x-admin.form.input class="col-md-6" name="icon" label="Icon" value="bi-grid" hint='Any <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Bootstrap icon</a>' />
                        <div class="col-md-6 d-flex align-items-end"><x-admin.form.checkbox name="web" label="Also create public website pages" /></div>
                        <div class="col-md-6 d-flex align-items-end"><x-admin.form.checkbox name="api" label="Also create a REST API (/api/v1/…)" /></div>
                        <div class="col-12">
                            <div class="terminal code"><span class="prompt">$</span> php artisan make:module Product --fields="…" --group="Modules" --icon=bi-grid --migrate</div>
                        </div>
                    </div>
                    <x-slot:footer>
                        @can('modules.create')
                            <button class="btn btn-primary" @disabled(! $canGenerate)><i class="bi bi-terminal me-1"></i> Generate module</button>
                        @endcan
                    </x-slot:footer>
                </x-admin.card>
            </form>
        </div>
        <div class="col-xl-6">
            <x-admin.card title="Generator output" class="h-100">
                @if (session('generator_output'))
                    <pre class="terminal small mb-0" style="white-space: pre-wrap">{{ session('generator_output') }}</pre>
                @else
                    <div class="text-secondary text-center border rounded-3 p-4" style="border-style: dashed !important">
                        Run the generator to see the files it creates. The new module gets its own permissions and appears in the sidebar right away.
                    </div>
                    <ul class="small text-secondary mt-3 mb-0">
                        <li>Model, migration, admin controller with search, pagination and bulk delete</li>
                        <li>Form requests with validation rules from your field types</li>
                        <li>Index, create and edit views using the admin components</li>
                        <li>Routes, <code>module.json</code>, permissions and sidebar item</li>
                        <li>Templates live in <code>/stubs/module</code>; edit them to change what gets generated</li>
                    </ul>
                @endif
            </x-admin.card>
        </div>
    </div>

    <x-admin.card title="Installed modules" :flush="true">
        <x-slot:actions><span class="badge badge-soft-secondary">{{ $modules->count() }} modules</span></x-slot:actions>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Module</th><th>Type</th><th>Version</th><th>Folder</th><th>Permissions</th><th class="text-end">Enabled</th></tr></thead>
                <tbody>
                @foreach ($modules as $module)
                    <tr>
                        <td><div class="fw-semibold">{{ $module->name }}</div><small class="text-secondary">{{ $module->description }}</small></td>
                        <td><span class="badge {{ $module->core ? 'badge-soft-secondary' : 'badge-soft-primary' }}">{{ $module->core ? 'Core' : 'Custom' }}</span></td>
                        <td class="text-secondary">{{ $module->version }}</td>
                        <td><code class="text-secondary">app/Modules/{{ basename($module->path) }}</code></td>
                        <td><code class="text-secondary small">{{ collect($module->permissions)->pluck('key')->map(fn ($k) => $k.'.*')->join(', ') }}</code></td>
                        <td class="text-end">
                            @if ($module->isLocked())
                                <span class="badge badge-soft-secondary">Required</span>
                            @else
                                @can('modules.edit')
                                    <form method="POST" action="{{ route('admin.modules.toggle', $module->slug) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <div class="form-check form-switch d-inline-block mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" @checked($module->enabled) data-autosubmit aria-label="Enable {{ $module->name }}">
                                        </div>
                                    </form>
                                @else
                                    <x-admin.status-badge :status="$module->enabled ? 'enabled' : 'disabled'" />
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection
