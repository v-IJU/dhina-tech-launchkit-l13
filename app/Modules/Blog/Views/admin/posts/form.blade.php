@extends('layouts.admin')

@php $editing = $post->exists; @endphp

@section('content')
    <x-admin.page-header :title="$editing ? 'Edit post' : 'Add new post'" :breadcrumbs="['Content', 'Blog posts' => route('admin.blog.posts.index'), $editing ? 'Edit' : 'Add']">
        @if ($editing)
            <a href="{{ route('blog.show', $post) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-eye me-1"></i> {{ $post->isLive() ? 'View' : 'Preview' }}</a>
        @endif
        <a href="{{ route('admin.blog.posts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </x-admin.page-header>

    <x-admin.translation-banner :record="$post" edit-route="admin.blog.posts.edit" />

    <form method="POST" action="{{ $editing ? route('admin.blog.posts.update', $post) : route('admin.blog.posts.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-xl-8 d-flex flex-column gap-4">
                <div class="card card-body d-flex flex-column gap-3">
                    <div>
                        <label for="title" class="form-label required">Title</label>
                        <input id="title" name="title" value="{{ old('title', $post->title) }}" placeholder="Post title" required
                               @class(['form-control form-control-lg fw-semibold', 'is-invalid' => $errors->has('title')])>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-admin.form.input name="slug" label="URL" prefix="/blog/" :value="$post->slug" data-slug-from="#title" hint="Leave empty to make it from the title." />
                    <x-admin.form.editor name="content" label="Content" :value="$post->content" required hint="HTML is cleaned with HTML Purifier when you save." />
                    <x-admin.form.textarea name="excerpt" label="Excerpt" :value="$post->excerpt" rows="3" maxlength="500" placeholder="Short summary for the blog list and social cards" />
                </div>

                <x-admin.card title="SEO" subtitle="How this post appears in Google and when shared.">
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <x-admin.form.input name="meta_title" id="meta_title" label="Meta title" :value="$post->meta_title" data-counter="60" data-preview-source="#gTitle" data-preview-fallback="#title" placeholder="Defaults to the post title" />
                            <div class="form-text" data-counter-for="meta_title"></div>
                        </div>
                        <div>
                            <x-admin.form.textarea name="meta_description" id="meta_description" label="Meta description" :value="$post->meta_description" rows="2" data-counter="160" data-preview-source="#gDesc" data-preview-fallback="#f_excerpt" />
                            <div class="form-text" data-counter-for="meta_description"></div>
                        </div>
                        <div class="google-preview">
                            <div class="small text-secondary fw-semibold mb-1" style="letter-spacing: .06em">GOOGLE PREVIEW</div>
                            <div class="url">{{ parse_url(config('app.url'), PHP_URL_HOST) }} › blog › <span data-slug-preview>{{ $post->slug ?: '…' }}</span></div>
                            <div class="title" id="gTitle" data-default="Post title">{{ $post->meta_title ?: ($post->title ?: 'Post title') }}</div>
                            <div class="small text-secondary" id="gDesc" data-default="Add a meta description so Google shows a helpful summary.">{{ $post->meta_description ?: ($post->excerpt ?: 'Add a meta description so Google shows a helpful summary.') }}</div>
                        </div>
                    </div>
                </x-admin.card>
            </div>

            <div class="col-xl-4 d-flex flex-column gap-4">
                <x-admin.card title="Publish">
                    <div class="d-flex flex-column gap-3">
                        <x-admin.form.select name="status" id="status" label="Status" :options="\App\Modules\Blog\Models\Post::STATUSES" :value="$post->status" />
                        <div data-show-when="#status" data-show-value="scheduled">
                            <x-admin.form.input type="datetime-local" name="published_at" label="Publish on" :value="$post->status === 'scheduled' ? $post->published_at?->format('Y-m-d\TH:i') : null" hint="The scheduler publishes it automatically." />
                        </div>
                        <x-admin.form.select name="author_id" label="Author" :options="$authors" :value="$post->author_id" required />
                        <x-admin.form.checkbox name="is_featured" label="Feature on the home page" :checked="$post->is_featured" />
                        @if ($editing)
                            <small class="text-secondary">
                                {{ $post->isLive() ? 'Live since '.$post->published_at->format('d M Y, H:i') : 'Not live yet' }} · {{ number_format($post->views) }} views · {{ $post->readingMinutes() }} min read
                            </small>
                        @endif
                        @cannot('blog.publish')
                            <div class="alert alert-info small mb-0">Your role can save drafts. Someone with publish permission will make it live.</div>
                        @endcannot
                    </div>
                    <x-slot:footer>
                        <button name="intent" value="draft" class="btn btn-outline-secondary me-auto">Save draft</button>
                        <button class="btn btn-primary">{{ $editing ? 'Update' : 'Save' }}</button>
                    </x-slot:footer>
                </x-admin.card>

                <x-admin.card title="Category & tags">
                    <div class="d-flex flex-column gap-3">
                        <x-admin.form.select name="category_id" label="Category" :options="$categories" :value="$post->category_id" placeholder="Select category" required
                            :hint="auth()->user()->can('categories.create') ? '<a href=\''.route('admin.blog.categories.create').'\'>Add a category</a>' : null" />
                        <x-admin.form.input name="tags" label="Tags" :value="implode(', ', $post->tags ?? [])" placeholder="laravel, business, tips" hint="Separate with commas." />
                    </div>
                </x-admin.card>

                <x-admin.card title="Featured image">
                    <x-admin.form.media name="featured_image" :value="$post->featured_image" hint="Pick or upload in the Media library. 1200 × 630 works best." />
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
