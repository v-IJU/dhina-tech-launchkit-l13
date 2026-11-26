<?php

declare(strict_types=1);

namespace App\Core\Import;

use App\Models\User;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

/**
 * What every LaunchKit import shares: heading row, chunk reading, and the bookkeeping in import_runs
 * (progress, created / updated / failed counts, skipped rows with the reason, done / failed status).
 *
 * Don't extend this directly. Pick one:
 *   ModuleImport  row by row:   rules() + saveRow()        → normal CRUD imports (FAQs, customers…)
 *   ChunkImport   chunk at once: importChunk($rows)        → bulk work against other tables (tags, prices, stock…)
 */
abstract class BaseImport implements WithChunkReading, WithEvents, WithHeadingRow
{
    /** Queue name for big imports (null = config/starter.php → imports.queue, "imports"). */
    public ?string $queue = null;

    private ?int $runId;

    private ?int $userId;

    private bool $dryRun;

    /** @var array<string, int> counts since the last chunk */
    private array $counts = [];

    /** @var list<array{row: int, messages: list<string>, values: array<string, mixed>}> */
    private array $errors = [];

    public function __construct(?ImportRun $run = null)
    {
        $this->runId = $run?->id;
        $this->userId = $run?->user_id;
        $this->dryRun = (bool) $run?->dry_run;
    }

    /**
     * Columns of the file: heading => example value (template, heading check, help table).
     *
     * @return array<string, string>
     */
    abstract public function columns(): array;

    public function chunkSize(): int
    {
        return max(50, (int) setting('import_chunk_size', 500));
    }

    /**
     * Help table on the import page.
     *
     * @return list<array{heading: string, required: bool, rules: string, example: string}>
     */
    public function describe(): array
    {
        $rules = $this->helpRules();

        return collect($this->columns())->map(fn ($example, $column) => [
            'heading' => self::heading((string) $column),
            'required' => in_array('required', is_string($rules[$column] ?? null) ? explode('|', $rules[$column]) : (array) ($rules[$column] ?? []), true),
            'rules' => collect((array) ($rules[$column] ?? []))->map(fn ($r) => is_string($r) ? $r : class_basename($r))->implode(', '),
            'example' => (string) $example,
        ])->values()->all();
    }

    /** "group_name" → "Group name", "id" → "ID" (exports use the same headings). */
    public static function heading(string $column): string
    {
        return $column === 'id' ? 'ID' : Str::ucfirst(str_replace('_', ' ', $column));
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterChunk::class => fn () => $this->flush(),
            AfterImport::class => function () {
                $this->flush();
                $this->run()?->finish();
            },
            ImportFailed::class => function (ImportFailed $event) {
                $this->flush();
                $this->run()?->fail($event->getException());
            },
        ];
    }

    /* ----- for the module class -------------------------------------------------- */

    /** Is this only a check? Skip side effects (emails…) when true. */
    protected function dryRun(): bool
    {
        return $this->dryRun;
    }

    /** Who uploaded the file (also inside a queued job, where there is no signed-in user). */
    protected function importer(): ?User
    {
        return $this->userId ? User::query()->find($this->userId) : null;
    }

    /** Count rows as created / updated (progress and the result message). */
    protected function created(int $rows = 1): void
    {
        $this->count('created_rows', $rows);
    }

    protected function updated(int $rows = 1): void
    {
        $this->count('updated_rows', $rows);
    }

    /** Rows read from the file (the progress bar). */
    protected function processed(int $rows = 1): void
    {
        $this->count('processed_rows', $rows);
    }

    /**
     * Skip a row with the reason. Listed on the result page and in the "Skipped rows" download.
     *
     * @param  string|list<string>  $reason
     * @param  iterable<string, mixed>  $values  the row as read from the file
     */
    protected function skip(int $line, string|array $reason, iterable $values = []): void
    {
        $this->count('failed_rows');
        $clean = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && $key !== '') {
                $clean[$key] = is_scalar($value) || $value === null ? $value : (string) json_encode($value);
            }
        }
        $this->errors[] = ['row' => $line, 'messages' => array_values((array) $reason), 'values' => $clean];
    }

    /** Column rules shown in the help table (ModuleImport: its rules()). @return array<string, mixed> */
    protected function helpRules(): array
    {
        return [];
    }

    /* ----- internals --------------------------------------------------------------- */

    private function count(string $column, int $rows = 1): void
    {
        $this->counts[$column] = ($this->counts[$column] ?? 0) + $rows;
    }

    /** Save counts and skipped rows of this chunk (in a queued import, each chunk is read in turn). */
    private function flush(): void
    {
        $run = $this->run();
        if ($run !== null) {
            $run->addCounts($this->counts);
            $room = ImportRun::MAX_ERRORS - $run->errors()->count();
            foreach (array_slice($this->errors, 0, max(0, $room)) as $error) {
                $run->errors()->create($error);
            }
        }
        $this->counts = [];
        $this->errors = [];
    }

    private function run(): ?ImportRun
    {
        return $this->runId ? ImportRun::query()->find($this->runId) : null;
    }
}
