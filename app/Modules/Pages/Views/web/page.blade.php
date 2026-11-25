@extends('layouts.web')

@section('title', $page->is_home ? '' : ($page->meta_title ?: $page->title))
@section('meta_description', $page->meta_description ?: '')
@section('og_image', $page->og_image ?: '')

@section('content')
    @if ($page->status !== 'published')
        <div class="bg-amber-50 text-amber-800"><div class="container-site py-2 text-sm font-medium">Draft preview — visitors cannot see this page yet.</div></div>
    @endif

    @forelse ($page->blocks ?? [] as $block)
        @includeIf('pages::web.blocks.'.$block['type'], ['data' => $block['data'] ?? [], 'first' => $loop->first])
    @empty
        <section class="section"><div class="container-site"><h1 class="section-title">{{ $page->title }}</h1></div></section>
    @endforelse
@endsection
