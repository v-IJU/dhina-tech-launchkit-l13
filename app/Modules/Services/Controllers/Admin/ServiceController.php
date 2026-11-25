<?php

declare(strict_types=1);

namespace App\Modules\Services\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Http\StoresUploads;
use App\Core\Import\CsvImport;
use App\Core\Import\HandlesImport;
use App\Http\Controllers\Controller;
use App\Modules\Services\Models\Service;
use App\Modules\Services\Requests\StoreServiceRequest;
use App\Modules\Services\Requests\UpdateServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Mews\Purifier\Facades\Purifier;
use Symfony\Component\HttpFoundation\Response;

class ServiceController extends Controller implements HasMiddleware
{
    use HandlesImport, StoresUploads;

    /** @var array<int, string> File inputs saved to storage/app/public/services */
    private array $uploads = [];

    /** @var array<int, string> Rich text fields cleaned with HTML Purifier */
    private array $richText = [];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:services.view', only: ['index']),
            new Middleware('permission:services.create', only: ['create', 'store']),
            new Middleware('permission:services.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:services.delete', only: ['destroy', 'restore', 'forceDelete']),
            new Middleware('permission:services.import', only: ['importForm', 'import', 'importTemplate']),
        ];
    }

    /**
     * List page. Search, sorting, filters, per-page, CSV export and bulk actions
     * all come from the query string; see app/Core/DataTable/DataTable.php.
     */
    public function index(Request $request): Response
    {
        return DataTable::of(Service::query(), $request)
            ->search(['title', 'icon', 'summary'])
            ->sortable(['title', 'icon', 'sort_order', 'is_active', 'created_at'], default: 'sort_order', direction: 'asc')
            ->status('is_active')
            ->dateRange('created_at')
            ->export('services', [
                'ID' => 'id',
                'Title' => 'title',
                'Icon' => 'icon',
                'Summary' => 'summary',
                'Sort order' => 'sort_order',
                'Is active' => 'is_active',
                'Created' => 'created_at',
            ], 'services.export')
            ->trash('services.delete')
            ->bulk(route('admin.services.bulk'), ['activate' => 'services.edit', 'deactivate' => 'services.edit', 'delete' => 'services.delete'])
            ->render('services::admin.index', 'services::admin._table');
    }

    public function create(): View
    {
        return view('services::admin.create', ['service' => new Service, 'options' => $this->options()]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $data = $this->prepare($request, $request->validated());
        Service::create($data);

        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function edit(Service $service): View
    {
        return view('services::admin.edit', ['service' => $service, 'options' => $this->options()]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $this->prepare($request, $request->validated(), $service);
        $service->update($data);

        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return back()->with('success', 'Service moved to the trash.');
    }

    public function restore(Service $service): RedirectResponse
    {
        $service->restore();

        return back()->with('success', 'Service restored.');
    }

    public function forceDelete(Service $service): RedirectResponse
    {
        $service->forceDelete(); // the model removes its uploaded files

        return back()->with('success', 'Service deleted forever.');
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
        abort_unless($request->user()->can($needsDelete ? 'services.delete' : 'services.edit'), 403);

        $inTrash = in_array($data['action'], ['restore', 'force-delete'], true);
        $items = Service::query()->when($inTrash, fn ($q) => $q->onlyTrashed())->whereKey($data['ids'])->get();

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

    public function toggle(Service $service): RedirectResponse
    {
        $service->update(['is_active' => ! $service->is_active]);

        return back()->with('success', 'Service '.($service->is_active ? 'activated' : 'deactivated').'.');
    }

    /**
     * Columns accepted by "Import CSV" (same headings as Export CSV).
     *
     * @return CsvImport<Service>
     */
    protected function importer(): CsvImport
    {
        return CsvImport::make(Service::class)
            ->column('title', ['required', 'string', 'max:255'], example: 'Web development')
            ->column('icon', ['nullable', 'string', 'max:255'], example: 'bi-code-slash')
            ->column('summary', ['nullable', 'string'])
            ->column('sort_order', ['nullable', 'integer'], example: '1')
            ->column('is_active', ['nullable', 'boolean'], example: 'Yes')
            ->matchBy('id');
    }

    /**
     * @return array{title: string, slug: string, back: string, breadcrumbs: array<int, string>}
     */
    protected function importPage(): array
    {
        return ['title' => 'Services', 'slug' => 'services', 'back' => route('admin.services.index'), 'breadcrumbs' => ['Website CMS', 'Services', 'Import']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, array $data, ?Service $service = null): array
    {
        foreach ($this->richText as $field) {
            if (isset($data[$field])) {
                $data[$field] = Purifier::clean($data[$field]);
            }
        }

        return $this->storeUploads($request, $data, $this->uploads, 'services', $service);
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
