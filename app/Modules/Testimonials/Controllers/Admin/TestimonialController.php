<?php

declare(strict_types=1);

namespace App\Modules\Testimonials\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Export\TemplateExport;
use App\Core\Http\StoresUploads;
use App\Core\Import\ImportRun;
use App\Http\Controllers\Controller;
use App\Modules\Testimonials\Exports\TestimonialsExport;
use App\Modules\Testimonials\Imports\TestimonialsImport;
use App\Modules\Testimonials\Models\Testimonial;
use App\Modules\Testimonials\Requests\StoreTestimonialRequest;
use App\Modules\Testimonials\Requests\UpdateTestimonialRequest;
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

class TestimonialController extends Controller implements HasMiddleware
{
    use StoresUploads;

    /** @var array<int, string> File inputs saved to storage/app/public/testimonials */
    private array $uploads = ['photo'];

    /** @var array<int, string> Rich text fields cleaned with HTML Purifier */
    private array $richText = [];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:testimonials.view', only: ['index']),
            new Middleware('permission:testimonials.create', only: ['create', 'store']),
            new Middleware('permission:testimonials.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:testimonials.delete', only: ['destroy', 'restore', 'forceDelete']),
            new Middleware('permission:testimonials.import', only: ['importForm', 'import', 'importTemplate']),
        ];
    }

    /**
     * List page. Search, sorting, filters, per-page, CSV export and bulk actions
     * all come from the query string; see app/Core/DataTable/DataTable.php.
     */
    public function index(Request $request): Response
    {
        return DataTable::of(Testimonial::query(), $request)
            ->search(['client_name', 'company', 'quote'])
            ->sortable(['client_name', 'company', 'rating', 'is_active', 'created_at'], default: 'created_at', direction: 'desc')
            ->status('is_active')
            ->dateRange('created_at')
            ->export('testimonials', TestimonialsExport::class, 'testimonials.export')   // app/Modules/Testimonials/Exports/TestimonialsExport.php
            ->trash('testimonials.delete')
            ->bulk(route('admin.testimonials.bulk'), ['activate' => 'testimonials.edit', 'deactivate' => 'testimonials.edit', 'delete' => 'testimonials.delete'])
            ->render('testimonials::admin.index', 'testimonials::admin._table');
    }

    public function create(): View
    {
        return view('testimonials::admin.create', ['testimonial' => new Testimonial, 'options' => $this->options()]);
    }

    public function store(StoreTestimonialRequest $request): RedirectResponse
    {
        $data = $this->prepare($request, $request->validated());
        Testimonial::create($data);

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial created.');
    }

    public function edit(Testimonial $testimonial): View
    {
        return view('testimonials::admin.edit', ['testimonial' => $testimonial, 'options' => $this->options()]);
    }

    public function update(UpdateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $data = $this->prepare($request, $request->validated(), $testimonial);
        $testimonial->update($data);

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial updated.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return back()->with('success', 'Testimonial moved to the trash.');
    }

    public function restore(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->restore();

        return back()->with('success', 'Testimonial restored.');
    }

    public function forceDelete(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->forceDelete(); // the model removes its uploaded files

        return back()->with('success', 'Testimonial deleted forever.');
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
        abort_unless($request->user()->can($needsDelete ? 'testimonials.delete' : 'testimonials.edit'), 403);

        $inTrash = in_array($data['action'], ['restore', 'force-delete'], true);
        $items = Testimonial::query()->when($inTrash, fn ($q) => $q->onlyTrashed())->whereKey($data['ids'])->get();

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

    public function toggle(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update(['is_active' => ! $testimonial->is_active]);

        return back()->with('success', 'Testimonial '.($testimonial->is_active ? 'activated' : 'deactivated').'.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, array $data, ?Testimonial $testimonial = null): array
    {
        foreach ($this->richText as $field) {
            if (isset($data[$field])) {
                $data[$field] = Purifier::clean($data[$field]);
            }
        }

        return $this->storeUploads($request, $data, $this->uploads, 'testimonials', $testimonial);
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

    /*
    | Import (Excel / CSV). Columns and saving: app/Modules/Testimonials/Imports/TestimonialsImport.php
    */
    public function importForm(): View
    {
        return view('testimonials::admin.import', [
            'columns' => (new TestimonialsImport)->describe(),
            'runs' => ImportRun::recent('testimonials'),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ImportRun::fileRules(), 'dry_run' => ['nullable', 'boolean']]);

        $run = ImportRun::start('testimonials', $request->file('file'), $request->user(), dryRun: $request->boolean('dry_run'));
        $run->process(new TestimonialsImport($run));   // small file: now · big file: queued in chunks

        return redirect()->route('admin.testimonials.import')->with(
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
        return Excel::download(new TemplateExport(new TestimonialsImport), 'testimonials-import-template.xlsx');
    }
}
