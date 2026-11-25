<?php

declare(strict_types=1);

namespace App\Core\Import;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Imports rows from a CSV file into a model, one row at a time, collecting
 * errors per row instead of stopping at the first bad line.
 *
 *   CsvImport::make(Faq::class)
 *       ->column('question', ['required', 'string', 'max:255'])
 *       ->column('sku', fn (?Model $existing) => ['required', Rule::unique('products', 'sku')->ignore($existing?->id)])
 *       ->column('is_active', ['boolean'])
 *       ->matchBy('id')                       // rows with a known id update that record
 *       ->run($request->file('file'));
 *
 * Headings are matched loosely: "Group name", "group_name" and "GROUP NAME" all
 * fill group_name. Unknown columns are ignored, so a file from "Export CSV"
 * can be edited and imported back.
 *
 * @template-covariant TModel of Model
 */
class CsvImport
{
    /** @var array<string, array{label: string, rules: array<int, mixed>|Closure, example: string}> */
    private array $columns = [];

    private string $matchBy = 'id';

    /** @var array<int, string> */
    private array $purify = [];

    private ?Closure $prepare = null;

    private ?Closure $save = null;

    /**
     * @param  class-string<TModel>  $model
     */
    public function __construct(private readonly string $model) {}

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return self<T>
     */
    public static function make(string $model): self
    {
        return new self($model);
    }

    /**
     * One importable column. $rules is a normal validation array, or a closure
     * receiving the existing record (null when creating) for unique rules.
     *
     * @param  array<int, mixed>|Closure  $rules
     * @return $this
     */
    public function column(string $attribute, array|Closure $rules, ?string $label = null, string $example = ''): static
    {
        $this->columns[$attribute] = [
            'label' => $label ?? Str::of($attribute)->replace('_', ' ')->ucfirst()->toString(),
            'rules' => $rules,
            'example' => $example,
        ];

        return $this;
    }

    /**
     * Column used to find an existing record to update ("id", "email", "sku"…).
     *
     * @return $this
     */
    public function matchBy(string $attribute): static
    {
        $this->matchBy = $attribute;

        return $this;
    }

    /**
     * Rich-text columns cleaned with HTML Purifier before saving.
     *
     * @param  array<int, string>  $attributes
     * @return $this
     */
    public function purify(array $attributes): static
    {
        $this->purify = $attributes;

        return $this;
    }

    /**
     * Change a row after validation, before saving: fn (array $data, ?Model $existing) => array.
     *
     * @return $this
     */
    public function prepare(Closure $callback): static
    {
        $this->prepare = $callback;

        return $this;
    }

    /**
     * Custom save: fn (array $data, ?Model $existing): Model. Default: update or create.
     *
     * @return $this
     */
    public function using(Closure $callback): static
    {
        $this->save = $callback;

        return $this;
    }

    /**
     * @return array<string, array{label: string, required: bool, rules: string, example: string}>
     */
    public function describe(): array
    {
        return collect($this->columns)->map(function (array $column) {
            $rules = $column['rules'] instanceof Closure ? ($column['rules'])(null) : $column['rules'];
            $text = collect($rules)->map(fn ($r) => is_string($r) ? $r : class_basename($r))->implode(', ');

            return [
                'label' => $column['label'],
                'required' => in_array('required', $rules, true),
                'rules' => $text,
                'example' => $column['example'],
            ];
        })->all();
    }

    /**
     * A CSV with the headings (and one example row) to fill in.
     */
    public function template(string $filename): StreamedResponse
    {
        $columns = $this->columns;
        $matchLabel = $this->matchBy === 'id' ? 'ID' : null;

        return response()->streamDownload(function () use ($columns, $matchLabel) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values(array_filter([$matchLabel, ...array_column($columns, 'label')])));
            if (collect($columns)->contains(fn ($c) => $c['example'] !== '')) {
                fputcsv($out, array_values(array_filter([$matchLabel ? '' : null, ...array_column($columns, 'example')], fn ($v) => $v !== null)));
            }
            fclose($out);
        }, $filename.'-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Import the file. With $dryRun everything is validated and saved inside a
     * transaction that is rolled back, so you can check a file first.
     */
    public function run(UploadedFile $file, bool $dryRun = false): ImportResult
    {
        $result = new ImportResult;
        $rows = $this->read($file->getRealPath(), $result);
        if ($rows === null) {
            return $result;
        }

        DB::beginTransaction();

        try {
            foreach ($rows as $line => $row) {
                $this->importRow($row, $line, $result);
            }
        } finally {
            $dryRun ? DB::rollBack() : DB::commit();
        }

        $result->dryRun = $dryRun;

        return $result;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(array $row, int $line, ImportResult $result): void
    {
        if (collect($row)->every(fn ($v) => trim($v) === '')) {
            $result->skipped++;

            return;
        }

        $existing = $this->findExisting($row);

        $data = [];
        $rules = [];
        $booleans = [];
        foreach ($this->columns as $attribute => $column) {
            $columnRules = $column['rules'] instanceof Closure ? ($column['rules'])($existing) : $column['rules'];

            if (! array_key_exists($attribute, $row)) {
                if (! $existing) {
                    $rules[$attribute] = $columnRules; // creating: "required" still applies to missing columns
                }

                continue; // missing column = keep the default / current value
            }

            $data[$attribute] = $this->normalise($row[$attribute], $columnRules);
            $rules[$attribute] = $columnRules;
            if (in_array('boolean', $columnRules, true)) {
                $booleans[] = $attribute;
            }
        }

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $result->addError($line, $validator->errors()->all(), $row);

            return;
        }

        $data = $validator->validated();
        foreach ($booleans as $attribute) {
            if (($data[$attribute] ?? null) === null) {
                unset($data[$attribute]); // empty yes/no cell = keep the default
            }
        }
        foreach ($this->purify as $attribute) {
            if (isset($data[$attribute]) && is_string($data[$attribute])) {
                $data[$attribute] = Purifier::clean($data[$attribute]);
            }
        }
        if ($this->prepare) {
            $data = ($this->prepare)($data, $existing);
        }

        try {
            DB::transaction(function () use ($data, $existing) {
                if ($this->save) {
                    ($this->save)($data, $existing);
                } elseif ($existing) {
                    $existing->update($data);
                } else {
                    $this->model::query()->create($data);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            $result->addError($line, ['Could not be saved: '.Str::limit($e->getMessage(), 160)], $row);

            return;
        }

        $existing ? $result->updated++ : $result->created++;
    }

    /**
     * @param  array<string, string>  $row
     * @return TModel|null
     */
    private function findExisting(array $row): ?Model
    {
        $value = trim($row[$this->matchBy] ?? '');
        if ($value === '') {
            return null;
        }

        return $this->model::query()->where($this->matchBy, $value)->first();
    }

    /**
     * Empty strings become null; yes/no style values become booleans for boolean columns.
     *
     * @param  array<int, mixed>  $rules
     */
    private function normalise(string $value, array $rules): mixed
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (in_array('boolean', $rules, true)) {
            $lower = strtolower($value);

            return match (true) {
                in_array($lower, ['1', 'yes', 'y', 'true', 'active', 'on'], true) => true,
                in_array($lower, ['0', 'no', 'n', 'false', 'inactive', 'off'], true) => false,
                default => $value,
            };
        }

        return $value;
    }

    /**
     * Read the file into [line number => [attribute => value]].
     *
     * @return array<int, array<string, string>>|null
     */
    private function read(string $path, ImportResult $result): ?array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // files saved by older Excel
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headings = fgetcsv($handle, null, $delimiter, '"', '');
        if (! $headings) {
            $result->fatal = 'The file is empty.';

            return null;
        }

        $known = array_keys($this->columns);
        $keys = array_map(fn ($h) => $this->headingToAttribute((string) $h, $known), $headings);
        if (! array_intersect($keys, $known)) {
            $result->fatal = 'None of the column headings match. Download the template to see the expected headings.';

            return null;
        }
        $result->ignoredColumns = array_values(array_filter($headings, fn ($h, $i) => ! in_array($keys[$i], [...$known, $this->matchBy], true) && trim((string) $h) !== '', ARRAY_FILTER_USE_BOTH));

        $rows = [];
        $line = 1;
        $max = (int) config('starter.import_max_rows', 5000);
        while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            if (count($rows) >= $max) {
                $result->fatal = "The file has more than {$max} rows. Split it into smaller files.";

                return null;
            }
            $row = [];
            foreach ($keys as $i => $key) {
                $row[$key] = (string) ($values[$i] ?? '');
            }
            $rows[$line] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * "Group name" / "GROUP_NAME" / "group-name" → group_name. "ID" → id.
     *
     * @param  array<int, string>  $known
     */
    private function headingToAttribute(string $heading, array $known): string
    {
        $key = Str::of($heading)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

        foreach ($this->columns as $attribute => $column) {
            if (Str::of($column['label'])->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString() === $key) {
                return $attribute;
            }
        }

        return $key;
    }
}
