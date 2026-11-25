<?php

declare(strict_types=1);

namespace App\Modules\Pages\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Localization\Locales;
use App\Http\Controllers\Controller;
use App\Modules\Pages\Models\Page;
use App\Modules\Pages\Support\BlockRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Mews\Purifier\Facades\Purifier;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:pages.view', only: ['index']),
            new Middleware('permission:pages.create', only: ['create', 'store', 'duplicate', 'translate']),
            new Middleware('permission:pages.edit', only: ['edit', 'update']),
            new Middleware('permission:pages.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): Response
    {
        return DataTable::of(Page::query()->originals()->with(['editor', 'translations']), $request)
            ->search(['title', 'slug'])
            ->sortable([
                'title' => fn ($query, string $dir) => $query->orderByDesc('is_home')->orderBy('title', $dir),
                'status' => 'status',
                'updated_at' => 'updated_at',
            ], default: 'title', direction: 'asc')
            ->status('status', ['published' => 'Published', 'draft' => 'Draft'])
            ->dateRange('updated_at', 'Updated')
            ->export('pages', [
                'ID' => 'id',
                'Title' => 'title',
                'URL' => fn (Page $p) => $p->is_home ? '/' : '/'.$p->slug,
                'Status' => 'status',
                'Blocks' => fn (Page $p) => count($p->blocks ?? []),
                'Updated' => 'updated_at',
                'Updated by' => 'editor.name',
            ], 'pages.view')
            ->render('pages::admin.index', 'pages::admin._table');
    }

    public function create(): View
    {
        return view('pages::admin.form', $this->formData(new Page(['status' => 'draft', 'blocks' => [
            ['type' => 'hero', 'data' => ['heading' => 'Page heading']],
            ['type' => 'text', 'data' => []],
        ]])));
    }

    public function store(Request $request): RedirectResponse
    {
        $page = Page::query()->create($this->validated($request));
        $this->afterSave($page);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page created.');
    }

    public function edit(Page $page): View
    {
        return view('pages::admin.form', $this->formData($page));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));
        $this->afterSave($page);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page saved. The website shows the new version now.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        if ($page->is_home) {
            return back()->with('error', 'Choose another home page before deleting this one.');
        }

        $page->delete();
        Cache::forget('sitemap.xml');

        return redirect()->route('admin.pages.index')->with('success', 'Page deleted.');
    }

    /**
     * Create (or open) the translation of a page in another website language.
     */
    public function translate(Page $page, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, Locales::translatable(), true) && ! $page->isTranslation(), 404);

        $translation = $page->translations()->where('locale', $locale)->first() ?? $page->makeTranslation($locale);

        return redirect()->route('admin.pages.edit', $translation)
            ->with('success', Locales::name($locale).' translation ready. Translate the text and publish it.');
    }

    public function duplicate(Page $page): RedirectResponse
    {
        $copy = $page->replicate(['is_home']);
        $copy->fill(['title' => $page->title.' (copy)', 'slug' => $this->uniqueSlug($page->slug.'-copy'), 'status' => 'draft', 'is_home' => false])->save();

        return redirect()->route('admin.pages.edit', $copy)->with('success', 'Page duplicated as a draft.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Page $page = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('title')))]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', 'not_in:'.implode(',', [config('starter.admin_path'), 'blog', 'contact-submit', 'newsletter', 'sitemap', 'robots']),
                Rule::unique('pages', 'slug')->ignore($page)],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_home' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'blocks' => ['nullable', 'array', 'max:40'],
            'blocks.*.type' => ['required', Rule::in(array_keys(BlockRegistry::all()))],
            'blocks.*.data' => ['nullable', 'array'],
        ], ['slug.not_in' => 'This URL is reserved. Pick another one.']);

        $data['blocks'] = $this->cleanBlocks($data['blocks'] ?? []);
        if ($page?->isTranslation()) {
            $data['is_home'] = false; // the original decides the home page
        }
        $data['updated_by'] = $request->user()->id;

        return $data;
    }

    /**
     * Keep only known fields for each block and clean rich text.
     *
     * @param  array<int|string, array<string, mixed>>  $blocks
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    private function cleanBlocks(array $blocks): array
    {
        $clean = [];
        foreach (array_values($blocks) as $block) {
            $definition = BlockRegistry::get($block['type']);
            $data = [];
            foreach ($definition['fields'] as $name => $field) {
                $value = $block['data'][$name] ?? null;
                $data[$name] = match ($field['type']) {
                    'editor' => $value ? Purifier::clean((string) $value) : null,
                    'toggle' => (bool) $value,
                    'number' => $value !== null && $value !== '' ? (int) $value : ($field['default'] ?? null),
                    default => is_string($value) ? Str::limit(trim($value), 5000, '') : null,
                };
            }
            $clean[] = ['type' => $block['type'], 'data' => $data];
        }

        return $clean;
    }

    private function afterSave(Page $page): void
    {
        if ($page->is_home) {
            Page::query()->whereKeyNot($page->id)->update(['is_home' => false]);
        }
        Cache::forget('sitemap.xml');
    }

    private function uniqueSlug(string $slug): string
    {
        $base = $slug;
        $i = 2;
        while (Page::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Page $page): array
    {
        return ['page' => $page, 'registry' => BlockRegistry::all()];
    }
}
