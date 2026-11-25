<?php

declare(strict_types=1);

namespace App\Modules\Blog\Resources;

use App\Core\Localization\Locales;
use App\Modules\Blog\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->originalRecord()->slug,
            'locale' => $this->locale ?? Locales::default(),
            'url' => route('blog.show', $this->resource),
            'excerpt' => $this->excerpt,
            'content' => $this->when($request->routeIs('api.blog.posts.show'), $this->content),
            'featured_image' => $this->featured_image,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'tags' => $this->tags ?? [],
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category?->id, 'name' => $this->category?->name, 'slug' => $this->category?->slug]),
            'author' => $this->whenLoaded('author', fn () => ['id' => $this->author?->id, 'name' => $this->author?->name]),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
