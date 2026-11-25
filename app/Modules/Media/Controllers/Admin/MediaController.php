<?php

declare(strict_types=1);

namespace App\Modules\Media\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Media\Models\MediaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaController extends Controller implements HasMiddleware
{
    /** Allowed uploads. SVG is left out on purpose: it can carry scripts. */
    public const MIMES = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,mp4,webm';

    public static function middleware(): array
    {
        return [
            new Middleware('permission:media.view', only: ['index', 'picker']),
            new Middleware('permission:media.create', only: ['store']),
            new Middleware('permission:media.edit', only: ['update']),
            new Middleware('permission:media.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), ['image', 'document', 'video'], true) ? $request->query('type') : null;

        $items = MediaItem::query()
            ->with('media')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $selected = $request->query('selected')
            ? MediaItem::query()->with(['media', 'uploader'])->find($request->query('selected'))
            : null;

        return view('media::admin.index', [
            'items' => $items,
            'type' => $type,
            'selected' => $selected,
            'counts' => MediaItem::query()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'mimes:'.self::MIMES, 'max:'.(config('starter.max_upload_kb') * 5)],
        ]);

        $last = null;
        foreach ($request->file('files') as $file) {
            $item = MediaItem::query()->create([
                'title' => Str::of($file->getClientOriginalName())->beforeLast('.')->limit(120)->toString(),
                'type' => MediaItem::typeFromMime((string) $file->getMimeType()),
                'uploaded_by' => $request->user()->id,
            ]);
            $item->addMedia($file)->usingFileName($this->safeName($file->getClientOriginalName()))->toMediaCollection('file');
            $last = $item;
        }

        return redirect()->route('admin.media.index', ['selected' => $last?->id])
            ->with('success', count($request->file('files')).' file(s) uploaded.');
    }

    public function update(Request $request, MediaItem $item): RedirectResponse
    {
        $item->update($request->validate([
            'title' => ['required', 'string', 'max:120'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('success', 'File details saved.');
    }

    public function destroy(MediaItem $item): RedirectResponse
    {
        $item->delete(); // the media library removes the files too

        return redirect()->route('admin.media.index')->with('success', 'File deleted.');
    }

    /**
     * JSON list of images for the media picker modal.
     */
    public function picker(Request $request): JsonResponse
    {
        $items = MediaItem::query()->with('media')->where('type', 'image')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->latest()->limit(60)->get();

        return response()->json(['data' => $items->map(fn (MediaItem $i) => [
            'id' => $i->id,
            'name' => $i->title,
            'alt' => $i->alt,
            'url' => $i->url(),
            'thumb' => $i->thumbUrl(),
        ])]);
    }

    private function safeName(string $original): string
    {
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        return Str::slug(pathinfo($original, PATHINFO_FILENAME)).'-'.Str::lower(Str::random(6)).'.'.$ext;
    }
}
