<?php

declare(strict_types=1);

namespace App\Modules\Faqs\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Export\TemplateExport;
use App\Core\Http\StoresUploads;
use App\Core\Import\ImportRun;
use App\Http\Controllers\Controller;
use App\Modules\Faqs\Exports\FaqsExport;
use App\Modules\Faqs\Imports\FaqsImport;
use App\Modules\Faqs\Models\Faq;
use App\Modules\Faqs\Requests\StoreFaqRequest;
use App\Modules\Faqs\Requests\UpdateFaqRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Mews\Purifier\Facades\Purifier;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class FaqController extends Controller implements HasMiddleware
{
    use StoresUploads;

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
     * List page. Search, sorting, filters, per-page, Excel/CSV export and bulk actions
     * all come from the query string; see app/Core/DataTable/DataTable.php.
     */
    public function index(Request $request): Response
    {
        return DataTable::of(Faq::query(), $request)
            ->search(['question', 'answer', 'group_name'])
            ->sortable(['question', 'group_name', 'sort_order', 'is_active', 'created_at'], default: 'sort_order', direction: 'asc')
            ->status('is_active')
            ->dateRange('created_at')
            ->export('faqs', FaqsExport::class, 'faqs.export')   // app/Modules/Faqs/Exports/FaqsExport.php
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

    /*
    | Import (Excel / CSV). The columns and saving are in app/Modules/Faqs/Imports/FaqsImport.php.
    */
    public function importForm(): View
    {
        return view('faqs::admin.import', [
            'columns' => (new FaqsImport)->describe(),
            'runs' => ImportRun::recent('faqs'),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ImportRun::fileRules(), 'dry_run' => ['nullable', 'boolean']]);

        $run = ImportRun::start('faqs', $request->file('file'), $request->user(), dryRun: $request->boolean('dry_run'));
        $run->process(new FaqsImport($run));   // small file: now · big file: queued in chunks

        return redirect()->route('admin.faqs.import')->with(
            $run->status === 'failed' ? 'error' : 'success',
            match (true) {
                $run->status === 'failed' => 'The file could not be imported: '.$run->message,
                $run->queued => 'Import started in the background ('.number_format($run->total_rows).' rows). You will get a notification when it is done.',
                $run->dry_run => 'Check finished, nothing was saved: '.$run->summary().'.',
                default => 'Import finished: '.$run->summary().'.',
            },
        );
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new TemplateExport(new FaqsImport), 'faqs-import-template.xlsx');
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
