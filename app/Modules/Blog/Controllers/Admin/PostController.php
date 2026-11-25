<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Localization\Locales;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Requests\PostRequest;
use App\Modules\Blog\Support\PostData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:blog.view', only: ['index']),
            new Middleware('permission:blog.create', only: ['create', 'store', 'translate']),
            new Middleware('permission:blog.edit', only: ['edit', 'update']),
            new Middleware('permission:blog.delete', only: ['destroy', 'restore', 'forceDelete']),
        ];
    }

    /**
     * Posts list (see app/Core/DataTable/DataTable.php for the query-string options).
     */
    public function index(Request $request): Response
    {
        $counts = Post::query()->originals()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $statuses = collect(Post::STATUSES)->map(fn ($label, $key) => $label.' ('.($counts[$key] ?? 0).')')->all();

        return DataTable::of(Post::query()->originals()->with(['category', 'author', 'translations']), $request)
            ->search(['title', 'slug', 'excerpt'])
            ->sortable(['title', 'status', 'published_at', 'updated_at', 'created_at'], default: 'updated_at')
            ->status('status', $statuses)
            ->filter('category', 'Category', Category::query()->orderBy('name')->pluck('name', 'id')->all(),
                fn ($query, string $id) => $query->where('category_id', $id))
            ->filter('featured', 'Featured', ['1' => 'Featured', '0' => 'Not featured'],
                fn ($query, string $value) => $query->where('is_featured', (bool) $value))
            ->dateRange('published_at', 'Published')
            ->export('posts', [
                'ID' => 'id',
                'Title' => 'title',
                'Slug' => 'slug',
                'Status' => 'status',
                'Category' => 'category.name',
                'Author' => 'author.name',
                'Featured' => 'is_featured',
                'Tags' => 'tags',
                'Published' => 'published_at',
                'Updated' => 'updated_at',
            ], 'blog.export')
            ->trash('blog.delete')
            ->bulk(route('admin.blog.posts.bulk'), ['publish' => 'blog.publish', 'draft' => 'blog.edit', 'delete' => 'blog.delete'])
            ->render('blog::admin.posts.index', 'blog::admin.posts._table');
    }

    /**
     * Bulk actions from the list: publish now, move back to draft, delete (to trash).
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['publish', 'draft', 'delete', 'restore', 'force-delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);
        abort_unless($request->user()->can(['publish' => 'blog.publish', 'draft' => 'blog.edit'][$data['action']] ?? 'blog.delete'), 403);

        $posts = Post::query()->when(in_array($data['action'], ['restore', 'force-delete'], true), fn ($q) => $q->onlyTrashed())->whereKey($data['ids'])->get();

        foreach ($posts as $post) {
            match ($data['action']) {
                'publish' => $post->update(['status' => 'published', 'published_at' => $post->published_at ?? now()]),
                'draft' => $post->update(['status' => 'draft']),
                'restore' => $post->restore(),
                'force-delete' => $post->forceDelete(),
                default => $post->delete(),
            };
        }

        $done = ['delete' => 'moved to the trash', 'restore' => 'restored', 'force-delete' => 'deleted forever'][$data['action']] ?? 'updated';

        return back()->with('success', $posts->count()." post(s) {$done}.");
    }

    public function create(Request $request): View
    {
        return view('blog::admin.posts.form', $this->formData(new Post([
            'status' => 'draft',
            'author_id' => $request->user()->id,
        ])));
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $post = Post::query()->create($this->postData($request));

        return redirect()->route('admin.blog.posts.edit', $post)->with('success', $this->message($post, 'created'));
    }

    public function edit(Post $post): View
    {
        return view('blog::admin.posts.form', $this->formData($post));
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $post->update($this->postData($request, $post));

        return redirect()->route('admin.blog.posts.edit', $post)->with('success', $this->message($post, 'saved'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('admin.blog.posts.index')->with('success', 'Post moved to the trash.');
    }

    /**
     * Create (or open) the translation of a post in another website language.
     */
    public function translate(Post $post, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, Locales::translatable(), true) && ! $post->isTranslation(), 404);

        $translation = $post->translations()->where('locale', $locale)->first() ?? $post->makeTranslation($locale);

        return redirect()->route('admin.blog.posts.edit', $translation)
            ->with('success', Locales::name($locale).' translation ready. Translate the text and publish it.');
    }

    public function restore(Post $post): RedirectResponse
    {
        $post->restore();

        return back()->with('success', 'Post restored.');
    }

    public function forceDelete(Post $post): RedirectResponse
    {
        $post->forceDelete();

        return back()->with('success', 'Post deleted forever.');
    }

    /**
     * @return array<string, mixed>
     */
    private function postData(PostRequest $request, ?Post $post = null): array
    {
        $data = PostData::fromRequest($request, $post);

        if ($request->input('status') !== 'draft' && $data['status'] === 'draft') {
            session()->flash('warning', 'You do not have permission to publish, so the post was saved as a draft.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Post $post): array
    {
        return [
            'post' => $post,
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id')->all(),
            'authors' => User::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    private function message(Post $post, string $verb): string
    {
        return match ($post->status) {
            'published' => "Post {$verb} and published to the website.",
            'scheduled' => "Post {$verb}. It goes live on ".$post->published_at?->format('d M Y, H:i').'.',
            default => "Draft {$verb}.",
        };
    }
}
