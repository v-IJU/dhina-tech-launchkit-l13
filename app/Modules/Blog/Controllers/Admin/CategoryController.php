<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:categories.view', only: ['index']),
            new Middleware('permission:categories.create', only: ['create', 'store']),
            new Middleware('permission:categories.edit', only: ['edit', 'update']),
            new Middleware('permission:categories.delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        return view('blog::admin.categories.index', [
            'categories' => Category::query()->withCount('posts')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('blog::admin.categories.form', ['category' => new Category]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::query()->create($this->validated($request));

        return redirect()->route('admin.blog.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('blog::admin.categories.form', ['category' => $category]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('admin.blog.categories.index')->with('success', 'Category saved.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete(); // posts keep existing, without a category

        return back()->with('success', 'Category deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        $request->merge(['slug' => $request->filled('slug') ? Str::slug((string) $request->input('slug')) : null]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:80', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        return array_filter($data, fn ($v, $k) => $k !== 'slug' || $v !== null, ARRAY_FILTER_USE_BOTH);
    }
}
