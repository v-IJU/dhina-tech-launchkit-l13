<?php

declare(strict_types=1);

namespace App\Modules\Sliders\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Http\StoresUploads;
use App\Core\Import\CsvImport;
use App\Core\Import\HandlesImport;
use App\Http\Controllers\Controller;
use App\Modules\Sliders\Models\Slider;
use App\Modules\Sliders\Requests\StoreSliderRequest;
use App\Modules\Sliders\Requests\UpdateSliderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Mews\Purifier\Facades\Purifier;
use Symfony\Component\HttpFoundation\Response;

class SliderController extends Controller implements HasMiddleware
{
    use HandlesImport, StoresUploads;

    /** @var array<int, string> File inputs saved to storage/app/public/sliders */
    private array $uploads = ['image'];

    /** @var array<int, string> Rich text fields cleaned with HTML Purifier */
    private array $richText = [];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:sliders.view', only: ['index']),
            new Middleware('permission:sliders.create', only: ['create', 'store']),
            new Middleware('permission:sliders.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:sliders.delete', only: ['destroy', 'restore', 'forceDelete']),
            new Middleware('permission:sliders.import', only: ['importForm', 'import', 'importTemplate']),
        ];
    }

    /**
     * List page. Search, sorting, filters, per-page, CSV export and bulk actions
     * all come from the query string; see app/Core/DataTable/DataTable.php.
     */
    public function index(Request $request): Response
    {
        return DataTable::of(Slider::query(), $request)
            ->search(['title', 'subtitle', 'button_text', 'button_url'])
            ->sortable(['title', 'subtitle', 'button_text', 'button_url', 'is_active', 'created_at'], default: 'sort_order', direction: 'asc')
            ->status('is_active')
            ->dateRange('created_at')
            ->export('sliders', [
                'ID' => 'id',
                'Title' => 'title',
                'Subtitle' => 'subtitle',
                'Button text' => 'button_text',
                'Button url' => 'button_url',
                'Sort order' => 'sort_order',
                'Is active' => 'is_active',
                'Created' => 'created_at',
            ], 'sliders.export')
            ->trash('sliders.delete')
            ->bulk(route('admin.sliders.bulk'), ['activate' => 'sliders.edit', 'deactivate' => 'sliders.edit', 'delete' => 'sliders.delete'])
            ->render('sliders::admin.index', 'sliders::admin._table');
    }

    public function create(): View
    {
        return view('sliders::admin.create', ['slider' => new Slider, 'options' => $this->options()]);
    }

    public function store(StoreSliderRequest $request): RedirectResponse
    {
        $data = $this->prepare($request, $request->validated());
        Slider::create($data);

        return redirect()->route('admin.sliders.index')->with('success', 'Slider created.');
    }

    public function edit(Slider $slider): View
    {
        return view('sliders::admin.edit', ['slider' => $slider, 'options' => $this->options()]);
    }

    public function update(UpdateSliderRequest $request, Slider $slider): RedirectResponse
    {
        $data = $this->prepare($request, $request->validated(), $slider);
        $slider->update($data);

        return redirect()->route('admin.sliders.index')->with('success', 'Slider updated.');
    }

    public function destroy(Slider $slider): RedirectResponse
    {
        $slider->delete();

        return back()->with('success', 'Slider moved to the trash.');
    }

    public function restore(Slider $slider): RedirectResponse
    {
        $slider->restore();

        return back()->with('success', 'Slider restored.');
    }

    public function forceDelete(Slider $slider): RedirectResponse
    {
        $slider->forceDelete(); // the model removes its uploaded files

        return back()->with('success', 'Slider deleted forever.');
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
        abort_unless($request->user()->can($needsDelete ? 'sliders.delete' : 'sliders.edit'), 403);

        $inTrash = in_array($data['action'], ['restore', 'force-delete'], true);
        $items = Slider::query()->when($inTrash, fn ($q) => $q->onlyTrashed())->whereKey($data['ids'])->get();

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

    public function toggle(Slider $slider): RedirectResponse
    {
        $slider->update(['is_active' => ! $slider->is_active]);

        return back()->with('success', 'Slider '.($slider->is_active ? 'activated' : 'deactivated').'.');
    }

    /**
     * Columns accepted by "Import CSV" (same headings as Export CSV).
     *
     * @return CsvImport<Slider>
     */
    protected function importer(): CsvImport
    {
        return CsvImport::make(Slider::class)
            ->column('title', ['required', 'string', 'max:255'], example: 'Spring offer')
            ->column('subtitle', ['nullable', 'string', 'max:255'])
            ->column('button_text', ['nullable', 'string', 'max:255'])
            ->column('button_url', ['nullable', 'string', 'max:255'])
            ->column('sort_order', ['nullable', 'integer'], example: '1')
            ->column('is_active', ['nullable', 'boolean'], example: 'Yes')
            ->matchBy('id');
    }

    /**
     * @return array{title: string, slug: string, back: string, breadcrumbs: array<int, string>}
     */
    protected function importPage(): array
    {
        return ['title' => 'Sliders', 'slug' => 'sliders', 'back' => route('admin.sliders.index'), 'breadcrumbs' => ['Website CMS', 'Sliders', 'Import']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, array $data, ?Slider $slider = null): array
    {
        foreach ($this->richText as $field) {
            if (isset($data[$field])) {
                $data[$field] = Purifier::clean($data[$field]);
            }
        }

        return $this->storeUploads($request, $data, $this->uploads, 'sliders', $slider);
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
