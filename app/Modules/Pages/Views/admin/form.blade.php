@extends('layouts.admin')

@php $editing = $page->exists; @endphp

@section('content')
    <x-admin.page-header :title="$editing ? 'Page builder: '.$page->title : 'Add page'" :breadcrumbs="['Website CMS', 'Pages' => route('admin.pages.index'), $editing ? 'Builder' : 'Add']">
        @if ($editing)
            <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-eye me-1"></i> View page</a>
        @endif
        <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <x-admin.translation-banner :record="$page" edit-route="admin.pages.edit" />

    @if ($errors->any())
        <div class="alert alert-danger">Please check: {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="d-flex flex-column gap-3" data-blocks>
                    @foreach (old('blocks', $page->blocks ?? []) as $i => $block)
                        @if (isset($registry[$block['type'] ?? '']))
                            @include('pages::admin._block', ['type' => $block['type'], 'data' => $block['data'] ?? [], 'token' => 'b'.$loop->index])
                        @endif
                    @endforeach
                </div>
                <div class="card text-center text-secondary p-5 {{ count($page->blocks ?? []) ? 'd-none' : '' }}" style="border-style: dashed" data-blocks-empty>
                    This page is empty. Add blocks from the panel on the right.
                </div>
            </div>

            <div class="col-xl-4 d-flex flex-column gap-4">
                <x-admin.card title="Page settings">
                    <div class="d-flex flex-column gap-3">
                        <x-admin.form.input name="title" id="page_title" label="Title" :value="$page->title" required />
                        <x-admin.form.input name="slug" label="URL" prefix="/" :value="$page->slug" data-slug-from="#page_title" hint="Leave empty to make it from the title." />
                        <x-admin.form.select name="status" label="Status" :options="['published' => 'Published', 'draft' => 'Draft (only staff can see it)']" :value="$page->status" />
                        @unless ($page->isTranslation())
                            <x-admin.form.checkbox name="is_home" label="Use as home page" :checked="$page->is_home" hint="Shown at / instead of its own URL." />
                        @endunless
                    </div>
                    <x-slot:footer>
                        <button class="btn btn-primary">{{ $editing ? 'Save page' : 'Create page' }}</button>
                    </x-slot:footer>
                </x-admin.card>

                <x-admin.card title="Add block" subtitle="Blocks appear in the order shown on the left.">
                    <div class="row g-2">
                        @foreach ($registry as $type => $def)
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-secondary w-100 text-start d-flex align-items-center gap-2" data-add-block="{{ $type }}">
                                    <i class="bi {{ $def['icon'] }}"></i><span class="small text-truncate">{{ $def['label'] }}</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>

                <x-admin.card title="SEO">
                    <div class="d-flex flex-column gap-3">
                        <x-admin.form.input name="meta_title" id="p_meta_title" label="Meta title" :value="$page->meta_title" data-counter="60" placeholder="Defaults to the page title" />
                        <div class="form-text mt-n2" data-counter-for="p_meta_title"></div>
                        <x-admin.form.textarea name="meta_description" id="p_meta_description" label="Meta description" :value="$page->meta_description" rows="2" data-counter="160" />
                        <div class="form-text mt-n2" data-counter-for="p_meta_description"></div>
                        <x-admin.form.media name="og_image" label="Social share image" :value="$page->og_image" />
                    </div>
                </x-admin.card>
            </div>
        </div>
    </form>

    {{-- Empty block templates used by "Add block". __TOKEN__ is replaced in the browser. --}}
    @foreach ($registry as $type => $def)
        <template data-block-template="{{ $type }}">
            @include('pages::admin._block', ['type' => $type, 'data' => [], 'token' => '__TOKEN__'])
        </template>
    @endforeach
@endsection
