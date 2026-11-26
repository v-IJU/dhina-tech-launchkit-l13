<?php

declare(strict_types=1);

namespace App\Core\Import;

use App\Core\Tenancy\Tenancy;
use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * One uploaded import file. Used by every module's import() action:
 *
 *   $run = ImportRun::start('faqs', $request->file('file'), $request->user(), dryRun: $request->boolean('dry_run'));
 *   $run->process(new FaqsImport($run));
 *
 * Small files (Settings → Import & export → "Import in the background above") run right away and the
 * result shows on the page. Bigger files run as one job on the app's default queue (SQS, Redis…),
 * read chunk by chunk; the page shows a progress bar and the user gets a bell notification when done.
 *
 * @property int $id
 * @property string $module
 * @property int|null $user_id
 * @property string $file_name
 * @property string $path
 * @property string $status
 * @property bool $queued
 * @property bool $dry_run
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $created_rows
 * @property int $updated_rows
 * @property int $failed_rows
 * @property string|null $message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class ImportRun extends Model
{
    use Prunable;

    public const DISK = 'local';

    /** Stored error rows per import (all are counted; the first ones are kept to download). */
    public const MAX_ERRORS = 5000;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['queued' => 'boolean', 'dry_run' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ImportRunError, $this> */
    public function errors(): HasMany
    {
        return $this->hasMany(ImportRunError::class);
    }

    /** Validation for the upload field. @return list<string> */
    public static function fileRules(): array
    {
        return ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:'.((int) setting('import_max_mb', 20) * 1024)];
    }

    /** Last imports of a module, for its import page. @return Collection<int, ImportRun> */
    public static function recent(string $module, int $limit = 10): Collection
    {
        return static::query()->with('user:id,name')->where('module', $module)->latest('id')->limit($limit)->get();
    }

    /** Save the upload and decide: now or in the background. */
    public static function start(string $module, UploadedFile $file, ?User $user, bool $dryRun = false): self
    {
        $folder = 'imports/'.(Tenancy::tenant()?->getTenantKey() !== null ? 'tenant'.Tenancy::tenant()->getTenantKey() : 'central');
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'csv';
        $path = (string) Storage::disk(self::DISK)->putFileAs($folder, $file, Str::random(32).'.'.($extension === 'txt' ? 'csv' : $extension));

        $run = static::query()->create([
            'module' => $module,
            'user_id' => $user?->id,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'path' => $path,
            'dry_run' => $dryRun,
        ]);

        $run->total_rows = $run->countRows();
        // A check (dry run) always runs right away; it is rolled back at the end.
        $run->queued = ! $dryRun && config('queue.default') !== 'sync' && $run->total_rows > (int) setting('import_queue_above', 1000);
        $run->save();

        return $run;
    }

    /** Run the module's import class on this file. */
    public function process(BaseImport $import): self
    {
        $this->update(['status' => 'running', 'started_at' => now()]);
        $disk = self::DISK;

        // Wrong file? Stop before reading thousands of rows.
        try {
            $headings = array_filter((array) ((new HeadingRowImport)->toArray($this->path, $disk)[0][0] ?? []));
        } catch (Throwable $e) {
            $this->fail('This file could not be read. Use an Excel (.xlsx) or CSV file.');

            return $this->refresh();
        }
        if (! array_intersect($headings, array_keys($import->columns()))) {
            $expected = implode(', ', array_map(fn ($c) => BaseImport::heading((string) $c), array_keys($import->columns())));
            $this->fail("None of the column headings match. The first row must have headings such as: {$expected}. Download the template to see them.");

            return $this->refresh();
        }

        if ($this->queued) {
            // Big file: one job on the app's normal queue (config/queue.php default: sqs, redis…).
            RunImport::dispatch($import, $this->id);

            return $this;
        }

        // Small file: read it now, in this request. No queue involved, so anything the rows dispatch
        // (welcome emails, notifications) still goes to your queue as usual.
        try {
            Excel::import($import, $this->path, $disk);
        } catch (Throwable $e) {
            $this->fail($e);
        }

        return $this->refresh();
    }

    /** Rows in the file (minus the heading row), read from the file's header without loading it. */
    public function countRows(): int
    {
        try {
            $file = Storage::disk(self::DISK)->path($this->path);
            $reader = IOFactory::createReaderForFile($file);
            $info = method_exists($reader, 'listWorksheetInfo') ? $reader->listWorksheetInfo($file) : [];

            return max(0, (int) ($info[0]['totalRows'] ?? 0) - 1);
        } catch (Throwable) {
            return 0;
        }
    }

    /** Called by the import (BaseImport) after each chunk. @param array<string, int> $counts */
    public function addCounts(array $counts): void
    {
        foreach ($counts as $column => $n) {
            if ($n > 0) {
                static::query()->whereKey($this->id)->increment($column, $n);
            }
        }
    }

    public function finish(): void
    {
        $this->refresh();
        if ($this->status === 'failed') {
            return;
        }
        $this->update(['status' => 'done', 'finished_at' => now()]);
        if (! $this->dry_run && ($this->created_rows || $this->updated_rows)) {
            activity($this->module)->causedBy($this->user_id ? User::query()->find($this->user_id) : null)
                ->withProperties(['import' => $this->id, 'created' => $this->created_rows, 'updated' => $this->updated_rows, 'failed' => $this->failed_rows])
                ->event('import')->log('Imported '.$this->file_name);
        }
        $this->notifyUser();
    }

    public function fail(Throwable|string $error): void
    {
        $message = $error instanceof Throwable ? (app()->isProduction() ? 'Unexpected error, see Monitoring → Error log.' : $error->getMessage()) : $error;
        if ($error instanceof Throwable) {
            report($error);
        }
        $this->forceFill(['status' => 'failed', 'finished_at' => now(), 'message' => Str::limit($message, 1000)]);
        static::query()->whereKey($this->id)->update($this->only(['status', 'finished_at', 'message']));
        $this->notifyUser();
    }

    /** Bell notification, only for background imports (small ones show the result on the page). */
    private function notifyUser(): void
    {
        if (! $this->queued || $this->user_id === null) {
            return;
        }
        $user = User::query()->find($this->user_id);
        $label = Str::headline($this->module);
        $text = $this->status === 'failed' ? "{$label} import failed: {$this->file_name}" : "{$label} import finished: ".$this->summary();
        $url = route('admin.imports.show', $this->id);
        $user?->notify(new AdminAlert($text, $url, $this->status === 'failed' ? 'bi-x-octagon' : 'bi-file-earmark-check'));
    }

    public function summary(): string
    {
        return collect([
            'created' => $this->created_rows, 'updated' => $this->updated_rows, 'failed' => $this->failed_rows,
        ])->filter()->map(fn ($n, $k) => number_format($n).' '.$k)->implode(', ') ?: 'nothing to import';
    }

    public function percent(): int
    {
        return $this->status === 'done' ? 100 : ($this->total_rows > 0 ? min(99, (int) floor($this->processed_rows * 100 / $this->total_rows)) : 0);
    }

    /** Who may see this import: the person who ran it, or anyone with the module's import permission. */
    public function visibleTo(User $user): bool
    {
        return $this->user_id === $user->id || $user->can($this->module.'.import');
    }

    /** Uploaded files and logs older than 30 days are removed nightly (model:prune). */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(30));
    }

    protected function pruning(): void
    {
        Storage::disk(self::DISK)->delete($this->path);
    }
}
