<?php

declare(strict_types=1);

namespace App\Modules\Media\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Media\Controllers\Admin\MediaController as AdminMediaController;
use App\Modules\Media\Models\MediaItem;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

/**
 * Media library API: upload a photo from the app, then use its "url"
 * (e.g. as a blog post's featured_image).
 */
class MediaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:media.view', only: ['index']),
            new Middleware('permission:media.create', only: ['store']),
        ];
    }

    /**
     * Latest files (newest first, 60 max).
     */
    #[QueryParameter('q', description: 'Search by title', type: 'string')]
    #[QueryParameter('type', description: 'image, document or video', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $items = MediaItem::query()->with('media')
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->latest()->limit(60)->get();

        return response()->json(['data' => $items->map(fn (MediaItem $i) => $this->payload($i))]);
    }

    /**
     * Upload one file (multipart/form-data, field "file").
     *
     * Images, PDFs, Office files and short videos. Returns the public URL.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.AdminMediaController::MIMES, 'max:'.(config('starter.max_upload_kb') * 5)],
            'title' => ['nullable', 'string', 'max:120'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $item = MediaItem::query()->create([
            'title' => $request->input('title') ?: Str::of($file->getClientOriginalName())->beforeLast('.')->limit(120)->toString(),
            'alt' => $request->input('alt'),
            'type' => MediaItem::typeFromMime((string) $file->getMimeType()),
            'uploaded_by' => $request->user()->id,
        ]);
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $item->addMedia($file)->usingFileName($name.'-'.Str::lower(Str::random(6)).'.'.strtolower($file->getClientOriginalExtension()))->toMediaCollection('file');

        return response()->json(['data' => $this->payload($item->load('media'))], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MediaItem $item): array
    {
        return ['id' => $item->id, 'title' => $item->title, 'alt' => $item->alt, 'type' => $item->type, 'url' => $item->url(), 'thumb' => $item->thumbUrl()];
    }
}
