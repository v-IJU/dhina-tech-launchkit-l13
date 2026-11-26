<?php

declare(strict_types=1);

namespace App\Core\DataTable;

use App\Core\Export\ModuleExport;
use App\Core\Export\TableExport;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side list table: search, sort, filters, date range, per-page,
 * Excel/CSV export and bulk actions, driven by plain query-string parameters.
 *
 *   return DataTable::of(Faq::query(), $request)
 *       ->search(['question', 'answer'])
 *       ->sortable(['question', 'sort_order', 'created_at'], default: 'sort_order', direction: 'asc')
 *       ->status('is_active')
 *       ->dateRange('created_at')
 *       ->export('faqs', FaqsExport::class, 'faqs.export')   // or a quick column list: ['ID' => 'id', 'Question' => 'question']
 *       ->bulk(route('admin.faqs.bulk'), ['activate' => 'faqs.edit', 'deactivate' => 'faqs.edit', 'delete' => 'faqs.delete'])
 *       ->trash('faqs.delete')
 *       ->render('faqs::admin.index', 'faqs::admin._table');
 *
 * Query string: ?q=&sort=&dir=&per_page=&from=&to=&<filter>=&trashed=1&export=xlsx|csv
 * Requests with the "X-DataTable" header get only the table partial (used by datatable.js).
 *
 * @template TModel of Model
 */
class DataTable
{
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /** Labels and button styles for the standard bulk actions. */
    public const BULK_LABELS = [
        'activate' => ['Activate', 'btn-outline-success', 'bi-check-circle'],
        'deactivate' => ['Deactivate', 'btn-outline-secondary', 'bi-slash-circle'],
        'block' => ['Block', 'btn-outline-danger', 'bi-slash-circle'],
        'publish' => ['Publish', 'btn-outline-success', 'bi-send'],
        'draft' => ['Move to draft', 'btn-outline-secondary', 'bi-file-earmark'],
        'delete' => ['Delete', 'btn-outline-danger', 'bi-trash'],
        'restore' => ['Restore', 'btn-outline-success', 'bi-arrow-counterclockwise'],
        'force-delete' => ['Delete forever', 'btn-outline-danger', 'bi-x-octagon'],
        'resolve' => ['Mark as fixed', 'btn-outline-success', 'bi-check2-circle'],
        'retry' => ['Retry', 'btn-outline-primary', 'bi-arrow-repeat'],
    ];

    /** @var LengthAwarePaginator<int, TModel>|null */
    public ?LengthAwarePaginator $rows = null;

    /** @var array<int, string|Closure> */
    private array $searchColumns = [];

    /** @var array<string, string|Closure> sort key => column or closure($query,) */
    private array $sortable = [];

    private ?string $defaultSort = null;

    private string $defaultDirection = 'desc';

    /** @var array<string, array{label: string, options: array<int|string, string>, apply: Closure}> */
    private array $filters = [];

    private ?string $dateColumn = null;

    private string $dateLabel = 'Created';

    private ?string $exportName = null;

    /** @var array<string, string|Closure>|class-string<ModuleExport> */
    private array|string $exportColumns = [];

    private ?string $exportPermission = null;

    private ?string $bulkUrl = null;

    /** @var array<string, string|null> action => permission */
    private array $bulkActions = [];

    private ?int $perPage = null;

    private ?string $trashPermission = null;

    private bool $trash = false;

    /**
     * @param  Builder<TModel>  $query
     */
    public function __construct(private readonly Builder $query, private readonly Request $request) {}

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return self<T>
     */
    public static function of(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /**
     * Columns matched with LIKE against ?q=. A closure receives ($query, $term) for custom search.
     *
     * @param  array<int, string|Closure>|Closure  $columns
     * @return $this
     */
    public function search(array|Closure $columns): static
    {
        $this->searchColumns = is_array($columns) ? $columns : [$columns];

        return $this;
    }

    /**
     * Columns that can be sorted with ?sort=&dir=. Keys can map to a column name
     * or a closure($query, $direction) for computed sorting.
     *
     * @param  array<int|string, string|Closure>  $columns
     * @return $this
     */
    public function sortable(array $columns, ?string $default = null, string $direction = 'desc'): static
    {
        foreach ($columns as $key => $column) {
            $this->sortable[is_int($key) ? (string) $column : $key] = $column;
        }
        if ($default !== null && ! array_key_exists($default, $this->sortable)) {
            $this->sortable[$default] = $default; // the default order is always allowed
        }
        $this->defaultSort = $default;
        $this->defaultDirection = $direction === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    /**
     * A dropdown filter. Without $apply, it filters "where $name = value".
     *
     * @param  array<int|string, string>  $options  value => label
     * @return $this
     */
    public function filter(string $name, string $label, array $options, ?Closure $apply = null): static
    {
        $this->filters[$name] = [
            'label' => $label,
            'options' => $options,
            'apply' => $apply ?? fn (Builder $q, string $value) => $q->where($q->qualifyColumn($name), $value),
        ];

        return $this;
    }

    /**
     * Status filter. Boolean column (is_active) by default; pass options for string columns.
     *
     * @param  array<int|string, string>|null  $options
     * @return $this
     */
    public function status(string $column = 'is_active', ?array $options = null): static
    {
        return $this->filter('status', 'Status', $options ?? ['1' => 'Active', '0' => 'Inactive'],
            fn (Builder $q, string $value) => $q->where($q->qualifyColumn($column), $options ? $value : (bool) $value));
    }

    /**
     * Date range filter (?from=&to=) on a date/datetime column.
     *
     * @return $this
     */
    public function dateRange(string $column = 'created_at', string $label = 'Created'): static
    {
        $this->dateColumn = $column;
        $this->dateLabel = $label;

        return $this;
    }

    /**
     * Enable "Export" (Excel or CSV) with the current filters and sorting. $columns is either the module's
     * export class (FaqsExport::class, extends App\Core\Export\ModuleExport) or a quick list
     * heading => attribute (dot notation for relations) or closure($row).
     *
     * @param  array<string, string|Closure>|class-string<ModuleExport>  $columns
     * @return $this
     */
    public function export(string $filename, array|string $columns, ?string $permission = null): static
    {
        $this->exportName = $filename;
        $this->exportColumns = $columns;
        $this->exportPermission = $permission;

        return $this;
    }

    /**
     * Bulk action bar. $actions: action => permission (null = anyone who can see the list).
     * Standard actions: activate, deactivate, delete. Others get a headline label.
     *
     * @param  array<string, string|null>  $actions
     * @return $this
     */
    public function bulk(string $url, array $actions): static
    {
        $this->bulkUrl = $url;
        $this->bulkActions = $actions;

        return $this;
    }

    /**
     * Adds "Active | Trash" tabs for soft-deleted rows (?trashed=1). In the trash,
     * the bulk bar offers Restore and Delete forever (both need $permission).
     * The model must use SoftDeletes.
     *
     * @return $this
     */
    public function trash(?string $permission = null): static
    {
        $this->trash = in_array(SoftDeletes::class, class_uses_recursive($this->query->getModel()), true);
        $this->trashPermission = $permission;

        return $this;
    }

    /**
     * @return $this
     */
    public function perPage(int $default): static
    {
        $this->perPage = $default;

        return $this;
    }

    /**
     * Full page, table partial (AJAX) or CSV download, depending on the request.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, string $partial, array $data = []): Response
    {
        $format = $this->request->query('export');
        if (in_array($format, ['xlsx', 'csv'], true) && $this->canExport()) {
            return $this->download($format);
        }

        $this->rows = $this->apply()->paginate($this->currentPerPage())->withQueryString();

        // Asked for a page past the end (e.g. after deleting the last row): go to the last page.
        if ($this->rows->isEmpty() && $this->rows->currentPage() > 1) {
            $this->rows = $this->apply()->paginate($this->currentPerPage(), page: $this->rows->lastPage())->withQueryString();
        }

        $data = ['table' => $this] + $data;

        if ($this->request->hasHeader('X-DataTable')) {
            return response(View::make($partial, $data)->render())->header('Vary', 'X-DataTable');
        }

        return response(View::make($view, $data)->render())->header('Vary', 'X-DataTable');
    }

    /**
     * Same search / filters / sort / per_page / page, returned as paginated JSON for the API:
     * {"data": [...], "links": {...}, "meta": {"current_page": 1, "total": 57, ...}}
     *
     * @param  class-string<JsonResource>|null  $resource  e.g. FaqResource::class (default: model attributes)
     * @param  Closure|null  $map  change each row first, e.g. fn ($post) => $post->localized('ta')
     */
    public function json(?string $resource = null, ?Closure $map = null): JsonResponse
    {
        $this->rows = $this->apply()->paginate($this->currentPerPage())->withQueryString();
        if ($map) {
            $this->rows->setCollection($this->rows->getCollection()->map($map));
        }
        $resource ??= JsonResource::class;

        return $resource::collection($this->rows)->response();
    }

    /**
     * The filtered + sorted query (also usable on its own, e.g. for custom exports).
     *
     * @return Builder<TModel>
     */
    public function apply(): Builder
    {
        $query = clone $this->query;

        if ($this->inTrash()) {
            $query->onlyTrashed(); // @phpstan-ignore method.notFound (SoftDeletes macro)
        }

        $term = trim((string) $this->request->query('q', ''));
        if ($term !== '' && $this->searchColumns) {
            $query->where(function (Builder $q) use ($term) {
                foreach ($this->searchColumns as $column) {
                    if ($column instanceof Closure) {
                        $column($q, $term);
                    } else {
                        $q->orWhere($q->qualifyColumn($column), 'like', '%'.addcslashes($term, '%_\\').'%');
                    }
                }
            });
        }

        foreach ($this->filters as $name => $filter) {
            $value = $this->filterValue($name);
            if ($value !== null) {
                ($filter['apply'])($query, $value);
            }
        }

        if ($this->dateColumn) {
            $column = $query->qualifyColumn($this->dateColumn);
            if ($from = $this->date('from')) {
                $query->where($column, '>=', $from->startOfDay());
            }
            if ($to = $this->date('to')) {
                $query->where($column, '<=', $to->endOfDay());
            }
        }

        $sort = $this->currentSort();
        if ($sort !== null) {
            $column = $this->sortable[$sort];
            $column instanceof Closure
                ? $column($query, $this->currentDirection())
                : $query->orderBy($query->qualifyColumn($column), $this->currentDirection());
        }

        return $query->orderBy($query->qualifyColumn($query->getModel()->getKeyName()), 'desc');
    }

    // ----- State used by the Blade components ---------------------------------

    public function currentSort(): ?string
    {
        $sort = (string) $this->request->query('sort', '');

        return array_key_exists($sort, $this->sortable) ? $sort : $this->defaultSort;
    }

    public function currentDirection(): string
    {
        $dir = $this->request->query('dir');

        if (! in_array($dir, ['asc', 'desc'], true) || ! array_key_exists((string) $this->request->query('sort'), $this->sortable)) {
            return $this->defaultDirection;
        }

        return $dir;
    }

    public function isSortable(string $key): bool
    {
        return array_key_exists($key, $this->sortable);
    }

    /**
     * URL that sorts by $key (toggles direction when already sorted by it).
     */
    public function sortUrl(string $key): string
    {
        $dir = $this->currentSort() === $key && $this->currentDirection() === 'asc' ? 'desc' : 'asc';

        return $this->url(['sort' => $key, 'dir' => $dir, 'page' => null]);
    }

    public function currentPerPage(): int
    {
        $value = (int) $this->request->query('per_page');

        return in_array($value, $this->perPageOptions(), true) ? $value : $this->defaultPerPage();
    }

    /**
     * Choices for the "Show [n]" dropdown: 10/25/50/100 plus the default if it differs.
     *
     * @return array<int, int>
     */
    public function perPageOptions(): array
    {
        $options = array_unique([...self::PER_PAGE_OPTIONS, $this->defaultPerPage()]);
        sort($options);

        return $options;
    }

    private function defaultPerPage(): int
    {
        return $this->perPage ?? (int) config('starter.per_page', 10);
    }

    /**
     * @return array<string, array{label: string, options: array<int|string, string>, value: ?string}>
     */
    public function filters(): array
    {
        return collect($this->filters)
            ->map(fn (array $f, string $name) => ['label' => $f['label'], 'options' => $f['options'], 'value' => $this->filterValue($name)])
            ->all();
    }

    public function hasDateRange(): bool
    {
        return $this->dateColumn !== null;
    }

    public function dateLabel(): string
    {
        return $this->dateLabel;
    }

    public function hasSearch(): bool
    {
        return $this->searchColumns !== [];
    }

    public function isFiltered(): bool
    {
        return $this->request->filled('q') || $this->request->filled('from') || $this->request->filled('to')
            || collect(array_keys($this->filters))->contains(fn ($name) => $this->filterValue($name) !== null);
    }

    public function hasTrash(): bool
    {
        return $this->trash && ($this->trashPermission === null || (bool) $this->request->user()?->can($this->trashPermission));
    }

    public function inTrash(): bool
    {
        return $this->hasTrash() && $this->request->query('trashed') === '1';
    }

    /**
     * Number of rows in the trash (for the tab badge).
     */
    public function trashCount(): int
    {
        if (! $this->hasTrash()) {
            return 0;
        }

        $query = clone $this->query;

        return $query->onlyTrashed()->count(); // @phpstan-ignore method.notFound
    }

    public function trashUrl(bool $trash): string
    {
        return $this->url(['trashed' => $trash ? '1' : null, 'page' => null]);
    }

    public function canExport(): bool
    {
        return $this->exportName !== null
            && ($this->exportPermission === null || (bool) $this->request->user()?->can($this->exportPermission));
    }

    public function exportUrl(string $format = 'xlsx'): string
    {
        return $this->url(['export' => $format, 'page' => null]);
    }

    public function resetUrl(): string
    {
        return $this->request->url().($this->inTrash() ? '?trashed=1' : '');
    }

    /**
     * Message for an empty table: trash, no matches, or nothing yet.
     */
    public function emptyMessage(string $label): string
    {
        return match (true) {
            $this->inTrash() => 'The trash is empty.',
            $this->isFiltered() => "No {$label} match these filters.",
            default => "No {$label} yet.",
        };
    }

    public function bulkUrl(): ?string
    {
        return $this->bulkUrl !== null && $this->bulkActions() ? $this->bulkUrl : null;
    }

    /**
     * Bulk actions the current user may run.
     *
     * @return array<string, array{label: string, class: string, icon: string}>
     */
    public function bulkActions(): array
    {
        $user = $this->request->user();
        $actions = $this->inTrash()
            ? ['restore' => $this->trashPermission, 'force-delete' => $this->trashPermission]
            : $this->bulkActions;

        return collect($actions)
            ->filter(fn (?string $permission) => $permission === null || (bool) $user?->can($permission))
            ->map(function (?string $permission, string $action) {
                [$label, $class, $icon] = self::BULK_LABELS[$action] ?? [str($action)->headline()->toString(), 'btn-outline-secondary', 'bi-lightning'];

                return ['label' => $label, 'class' => $class, 'icon' => $icon];
            })
            ->all();
    }

    /**
     * Current URL with some query parameters changed (null removes one).
     *
     * @param  array<string, mixed>  $changes
     */
    public function url(array $changes = []): string
    {
        $query = array_filter(array_merge($this->request->query(), $changes), fn ($v) => $v !== null && $v !== '');

        return $this->request->url().($query ? '?'.http_build_query($query) : '');
    }

    // ----- Internals -----------------------------------------------------------

    private function filterValue(string $name): ?string
    {
        $value = $this->request->query($name);

        return is_string($value) && $value !== '' && array_key_exists($value, $this->filters[$name]['options']) ? $value : null;
    }

    private function date(string $key): ?Carbon
    {
        $value = $this->request->query($key);
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function download(string $format): BinaryFileResponse
    {
        $query = $this->apply();
        $export = is_string($this->exportColumns)
            ? new ($this->exportColumns)($query)
            : new TableExport($query, $this->exportColumns);
        $export->csv = $format === 'csv';

        return Excel::download($export, $this->exportName.'-'.now()->format('Y-m-d-His').'.'.$format);
    }
}
