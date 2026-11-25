<?php

declare(strict_types=1);

namespace App\Modules\Ai\Mcp\Tools;

use App\Models\User;
use App\Modules\Blog\Models\Post;
use App\Modules\Pages\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Search the website\'s pages and blog posts by words in the title or text. Returns title, status, address and a short excerpt for each match.')]
class SearchContentTool extends SiteTool
{
    protected string $name = 'search-content';

    public function allows(?User $user): bool
    {
        return parent::allows($user) && $user !== null && (
            (module_enabled('pages') && $user->can('pages.view')) || (module_enabled('blog') && $user->can('blog.view'))
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Words to look for, e.g. "pricing" or "opening hours".')->required(),
            'limit' => $schema->integer()->description('Maximum results per type (1-20). Default 5.'),
        ];
    }

    public function run(array $input, User $user): array
    {
        $term = trim((string) ($input['query'] ?? ''));
        $limit = max(1, min(20, (int) ($input['limit'] ?? 5)));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $result = ['query' => $term];

        if (module_enabled('blog') && $user->can('blog.view')) {
            $result['blog_posts'] = Post::query()
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('content', 'like', $like))
                ->latest('updated_at')->limit($limit)->get()
                ->map(fn (Post $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'status' => $p->status,
                    'published_at' => $p->published_at?->toDateString(),
                    'url' => $p->isLive() ? route('blog.show', $p) : null,
                    'excerpt' => Str::limit(strip_tags((string) ($p->excerpt ?: $p->content)), 200),
                ])->all();
        }

        if (module_enabled('pages') && $user->can('pages.view')) {
            $result['pages'] = Page::query()
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('blocks', 'like', $like))
                ->latest('updated_at')->limit($limit)->get()
                ->map(fn (Page $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'status' => $p->status,
                    'url' => $p->status === 'published' ? url($p->is_home ? '/' : $p->slug) : null,
                ])->all();
        }

        return $result;
    }
}
