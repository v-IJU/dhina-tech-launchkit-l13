<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Api;

use App\Core\DataTable\DataTable;
use App\Core\Localization\Locales;
use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Requests\PostRequest;
use App\Modules\Blog\Resources\PostResource;
use App\Modules\Blog\Support\PostData;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Blog posts API: /api/v1/blog/posts  (list without "content"; one post with it)
 *
 *   GET /api/v1/blog/posts?status=published&category=2&q=docker&sort=published_at&dir=desc&per_page=20
 *   GET /api/v1/blog/posts/12?lang=ta     (translation when one is published, else the original)
 *   GET /api/v1/blog/categories
 */
class PostController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:blog.view', only: ['index', 'show', 'categories']),
            new Middleware('permission:blog.create', only: ['store']),
            new Middleware('permission:blog.edit', only: ['update']),
            new Middleware('permission:blog.delete', only: ['destroy']),
        ];
    }

    /**
     * List (paginated). Same search, filters and sorting as the admin list.
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<PostResource>>
     */
    #[QueryParameter('q', description: 'Search text', type: 'string')]
    #[QueryParameter('sort', description: 'Sort by: title, status, published_at, updated_at, created_at', type: 'string')]
    #[QueryParameter('dir', description: 'asc or desc', type: 'string')]
    #[QueryParameter('per_page', description: '10, 15, 25, 50 or 100', type: 'integer')]
    #[QueryParameter('page', type: 'integer')]
    #[QueryParameter('status', description: 'draft, published or scheduled', type: 'string')]
    #[QueryParameter('category', description: 'Category id', type: 'integer')]
    #[QueryParameter('featured', description: '1 = featured only, 0 = not featured', type: 'string')]
    #[QueryParameter('lang', description: 'Language code, e.g. ta: returns the published translation when there is one', type: 'string')]
    #[QueryParameter('from', description: 'From date (YYYY-MM-DD)', type: 'string', format: 'date')]
    #[QueryParameter('to', description: 'To date (YYYY-MM-DD)', type: 'string', format: 'date')]
    public function index(Request $request): JsonResponse
    {
        $lang = Locales::isEnabled($request->query('lang')) ? (string) $request->query('lang') : null;

        return DataTable::of(Post::query()->originals()->with(['category', 'author', 'translations']), $request)
            ->search(['title', 'slug', 'excerpt'])
            ->sortable(['title', 'status', 'published_at', 'updated_at', 'created_at'], default: 'updated_at')
            ->status('status', Post::STATUSES)
            ->filter('category', 'Category', Category::query()->pluck('name', 'id')->all(), fn ($q, string $id) => $q->where('category_id', $id))
            ->filter('featured', 'Featured', ['1' => 'Yes', '0' => 'No'], fn ($q, string $v) => $q->where('is_featured', (bool) $v))
            ->dateRange('published_at', 'Published')
            ->json(PostResource::class, $lang ? fn (Post $post) => $post->localized($lang) : null);
    }

    public function show(Request $request, Post $post): PostResource
    {
        $lang = Locales::isEnabled($request->query('lang')) ? (string) $request->query('lang') : null;

        return new PostResource($post->load(['category', 'author'])->localized($lang ?? Locales::default()));
    }

    public function store(PostRequest $request): JsonResponse
    {
        $post = Post::query()->create(PostData::fromRequest($request));

        return (new PostResource($post->load(['category', 'author'])))->response()->setStatusCode(201);
    }

    public function update(PostRequest $request, Post $post): PostResource
    {
        $post->update(PostData::fromRequest($request, $post));

        return new PostResource($post->refresh()->load(['category', 'author']));
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json(['message' => 'Post moved to the trash.']);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()->orderBy('name')->withCount('posts')->get(['id', 'name', 'slug'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'posts' => $c->posts_count]),
        ]);
    }
}
