<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::query()->published()->with(['category', 'author', 'translations'])
            ->when($request->string('tag')->toString(), fn ($q, $tag) => $q->whereJsonContains('tags', $tag))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();
        $posts->setCollection($posts->getCollection()->map->localized());

        return view('blog::web.index', [
            'posts' => $posts,
            'categories' => $this->categories(),
            'heading' => $request->query('tag') ? '#'.$request->query('tag') : 'Blog',
        ]);
    }

    public function category(Category $category): View
    {
        abort_unless($category->is_active, 404);

        $posts = $category->posts()->published()->with(['category', 'author', 'translations'])->latest('published_at')->paginate(9);
        $posts->setCollection($posts->getCollection()->map->localized());

        return view('blog::web.index', [
            'posts' => $posts,
            'categories' => $this->categories(),
            'heading' => $category->name,
            'current' => $category,
        ]);
    }

    public function show(Request $request, Post $post): View|RedirectResponse
    {
        // A translation's own slug: send visitors to the original URL in that language.
        if ($post->isTranslation()) {
            $slug = Post::query()->whereKey($post->translation_of)->value('slug');

            return redirect()->route('blog.show', ['post' => $slug, 'lang' => $post->locale], 301);
        }

        // Staff can preview drafts; visitors only see live posts.
        abort_unless($post->isLive() || $request->user()?->can('blog.view'), 404);

        if ($post->isLive()) {
            $post->increment('views');
        }

        return view('blog::web.show', [
            'post' => $post->load(['category', 'author'])->localized(),
            'related' => Post::query()->published()->with(['category', 'translations'])->whereKeyNot($post->id)
                ->where('category_id', $post->category_id)->latest('published_at')->limit(3)->get()->map->localized(),
        ]);
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories()
    {
        return Category::query()->where('is_active', true)
            ->withCount(['posts' => fn ($q) => $q->published()])->orderBy('name')->get();
    }
}
