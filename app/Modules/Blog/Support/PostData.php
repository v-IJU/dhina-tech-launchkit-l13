<?php

declare(strict_types=1);

namespace App\Modules\Blog\Support;

use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Requests\PostRequest;
use Mews\Purifier\Facades\Purifier;

/**
 * Turns a validated PostRequest into the attributes to save.
 * Shared by the admin controller and the API controller.
 */
final class PostData
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(PostRequest $request, ?Post $post = null): array
    {
        $data = $request->validated();
        $data['content'] = Purifier::clean((string) $data['content']);
        $tags = $data['tags'] ?? '';
        $data['tags'] = collect(is_array($tags) ? $tags : explode(',', (string) $tags))->map(fn ($t) => trim((string) $t))->filter()->unique()->values()->all();
        $data['slug'] = ($data['slug'] ?? null) ?: $post?->slug;
        $data['author_id'] = ($data['author_id'] ?? null) ?: ($post !== null ? $post->author_id : $request->user()?->id);

        // Without the publish permission a post can only be saved as a draft.
        if ($data['status'] !== 'draft' && ! $request->user()?->can('blog.publish')) {
            $data['status'] = 'draft';
        }

        $data['published_at'] = match ($data['status']) {
            'published' => $post?->published_at && $post->published_at->isPast() ? $post->published_at : now(),
            'scheduled' => $data['published_at'],
            default => null,
        };

        return array_filter($data, fn ($v, $k) => $k !== 'slug' || $v !== null, ARRAY_FILTER_USE_BOTH);
    }
}
