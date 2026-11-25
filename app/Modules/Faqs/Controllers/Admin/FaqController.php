<?php

declare(strict_types=1);

namespace App\Modules\Faqs\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Http\StoresUploads;
use App\Core\Import\CsvImport;
use App\Core\Import\HandlesImport;
use App\Http\Controllers\Controller;
use App\Modules\Faqs\Models\Faq;
use App\Modules\Faqs\Requests\StoreFaqRequest;
use App\Modules\Faqs\Requests\UpdateFaqRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Mews\Purifier\Facades\Purifier;
use Symfony\Component\HttpFoundation\Response;

class FaqController extends Controller implements HasMiddleware
{
    use HandlesImport, StoresUploads;

    /** @var array<int, string> File inputs saved to storage/app/public/faqs */
    private array $uploads = [];

    /** @var array<int, string> Rich text fields cleaned with HTML Purifier */
    private array $richText = [];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:faqs.view', only: ['index']),
            new Middleware('permission:faqs.create', only: ['create', 'store']),
            new Middleware('permission:faqs.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:faqs.delete', only: ['destroy', 'restore', 'forceDelete']),
            new Middleware('permission:faqs.import', only: ['importForm', 'import', 'importTemplate']),
        ];
    }

    /**
     * List page. Search, sorting, filters, per-page, CSV export and bulk actions
     * all come from the query string; see app/Core/DataTable/DataTable.php.
     */
    public function index(Request $request): Response
    {
        return DataTable::of(Faq::query(), $request)
            ->search(['question', 'answer', 'group_name'])
            ->sortable(['question', 'group_name', 'sort_order', 'is_active', 'created_at'], default: 'sort_order', direction: 'asc')
            ->status('is_active')
            ->dateRange('created_at')
            ->export('faqs', [
                'ID' => 'id',
                'Question' => 'question',
                'Answer' => 'answer',
                'Group name' => 'group_name',
                'Sort order' => 'sort_order',
                'Is active' => 'is_active',
                'Created' => 'created_at',
            ], 'faqs.export')
            ->trash('faqs.delete')
            ->bulk(route('admin.faqs.bulk'), ['activate' => 'faqs.edit', 'deactivate' => 'faqs.edit', 'delete' => 'faqs.delete'])
            ->render('faqs::admin.index', 'faqs::admin._table');
    }

    public function create(): View
    {
        return view('faqs::admin.create', ['faq' => new Faq, 'options' => $this->options()]);
    }

    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $data = $this->prepare($request, $request->validated());
        Faq::create($data);

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ created.');
    }

    public function edit(Faq $faq): View
    {
        return view('faqs::admin.edit', ['faq' => $faq, 'options' => $this->options()]);
    }

    public function update(UpdateFaqRequest $request, Faq $faq): RedirectResponse
    {
        $data = $this->prepare($request, $request->validated(), $faq);
        $faq->update($data);

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ updated.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', 'FAQ moved to the trash.');
    }

    public function restore(Faq $faq): RedirectResponse
    {
        $faq->restore();

        return back()->with('success', 'FAQ restored.');
    }

    public function forceDelete(Faq $faq): RedirectResponse
    {
        $faq->forceDelete(); // the model removes its uploaded files

        return back()->with('success', 'FAQ deleted forever.');
    }

    /**
     * Bulk actions from the list page.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete', 'restore', 'force-delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);
        $needsDelete = in_array($data['action'], ['delete', 'restore', 'force-delete'], true);
        abort_unless($request->user()->can($needsDelete ? 'faqs.delete' : 'faqs.edit'), 403);

        $inTrash = in_array($data['action'], ['restore', 'force-delete'], true);
        $items = Faq::query()->when($inTrash, fn ($q) => $q->onlyTrashed())->whereKey($data['ids'])->get();

        match ($data['action']) {
            'activate' => $items->each->update(['is_active' => true]),
            'deactivate' => $items->each->update(['is_active' => false]),
            'delete' => $items->each->delete(),
            'restore' => $items->each->restore(),
            'force-delete' => $items->each->forceDelete(),
            default => null,
        };

        $done = ['delete' => 'moved to the trash', 'restore' => 'restored', 'force-delete' => 'deleted forever'][$data['action']] ?? 'updated';

        return back()->with('success', $items->count()." item(s) {$done}.");
    }

    public function toggle(Faq $faq): RedirectResponse
    {
        $faq->update(['is_active' => ! $faq->is_active]);

        return back()->with('success', 'FAQ '.($faq->is_active ? 'activated' : 'deactivated').'.');
    }

    /**
     * Columns accepted by "Import CSV" (same headings as Export CSV).
     *
     * @return CsvImport<Faq>
     */
    protected function importer(): CsvImport
    {
        return CsvImport::make(Faq::class)
            ->column('question', ['required', 'string', 'max:255'], example: 'How long does a project take?')
            ->column('answer', ['required', 'string'], example: 'Usually 4 to 8 weeks.')
            ->column('group_name', ['nullable', 'string', 'max:255'], example: 'General')
            ->column('sort_order', ['nullable', 'integer'], example: '1')
            ->column('is_active', ['nullable', 'boolean'], example: 'Yes')
            ->matchBy('id');
    }

    /**
     * @return array{title: string, slug: string, back: string, breadcrumbs: array<int, string>}
     */
    protected function importPage(): array
    {
        return ['title' => 'FAQs', 'slug' => 'faqs', 'back' => route('admin.faqs.index'), 'breadcrumbs' => ['Website CMS', 'FAQs', 'Import']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, array $data, ?Faq $faq = null): array
    {
        foreach ($this->richText as $field) {
            if (isset($data[$field])) {
                $data[$field] = Purifier::clean($data[$field]);
            }
        }

        return $this->storeUploads($request, $data, $this->uploads, 'faqs', $faq);
    }

    /**
     * Options for select inputs (related records).
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [

        ];
    }
}
