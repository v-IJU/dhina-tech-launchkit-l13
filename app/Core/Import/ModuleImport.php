<?php

declare(strict_types=1);

namespace App\Core\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Row-by-row import (Laravel Excel). For bulk work on a whole chunk at once, see ChunkImport.
 * A module import only says:
 *
 *   protected string $model = Faq::class;          // the model (and $matchBy = 'id' to find existing rows)
 *   public function columns(): array               // heading => example, for the template and help table
 *   public function rules(?Model $existing): array // normal validation rules per column
 *   public function saveRow(array $data, ?Model $existing): void   // create or update one row
 *
 * Everything else is here: heading row ("Group name" = group_name), Yes/No → true/false, Excel dates,
 * validation per row (bad rows are skipped and listed), chunk reading (Settings → Import & export),
 * progress + counts in import_runs, and the "only check" mode. Big files: ImportRun dispatches
 * App\Core\Import\RunImport on your default queue, which runs this same class chunk by chunk.
 *
 * Need more? Any Maatwebsite concern works in the module class (WithMultipleSheets, WithStartRow…),
 * or override find(), chunkSize(), normalise().
 */
abstract class ModuleImport extends BaseImport implements OnEachRow, SkipsEmptyRows
{
    /** @var class-string<Model>|null Model used by the default find(). */
    protected ?string $model = null;

    /** Column that finds an existing record to update ("id", "email", "sku"…). */
    protected string $matchBy = 'id';

    /**
     * Validation rules. $existing = the record this row will update, null when it creates one.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(?Model $existing): array;

    /**
     * Save one valid row. $data has only the columns that are in the file.
     *
     * @param  array<string, mixed>  $data
     */
    abstract public function saveRow(array $data, ?Model $existing): void;

    /**
     * The record a row updates, or null to create one. Default: $model where $matchBy = the row's value.
     *
     * @param  array<string, mixed>  $row
     */
    public function find(array $row): ?Model
    {
        $value = $row[$this->matchBy] ?? null;
        if ($this->model === null || $value === null || $value === '') {
            return null;
        }
        $query = $this->model::query();
        if (method_exists($this->model, 'bootSoftDeletes')) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query->where($this->matchBy, $value)->first();
    }

    /** @return array<string, mixed> */
    protected function helpRules(): array
    {
        return $this->rules(null);
    }

    public function onRow(Row $row): void
    {
        $values = array_filter($row->toArray(), fn ($key) => is_string($key) && $key !== '', ARRAY_FILTER_USE_KEY);
        $line = $row->getIndex();
        $this->processed();

        try {
            $existing = $this->find($values);
            $rules = $this->rules($existing);
            if ($existing !== null) {
                $rules = array_intersect_key($rules, $values); // updating: columns not in the file stay as they are
            }
            $data = $this->normalise(array_intersect_key($values, $rules), $rules);

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $this->skip($line, $validator->errors()->all(), $values);

                return;
            }
            $valid = array_filter($validator->validated(), fn ($v, $k) => ! ($v === null && $this->hasRule($rules[$k] ?? [], 'boolean')), ARRAY_FILTER_USE_BOTH);

            // Each row in its own savepoint: a failing row never undoes the others. "Only check" undoes every row.
            DB::beginTransaction();
            try {
                $this->saveRow($valid, $existing);
                $this->dryRun() ? DB::rollBack() : DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            $existing ? $this->updated() : $this->created();
        } catch (Throwable $e) {
            report($e);
            $this->skip($line, [app()->isProduction() ? 'Could not save this row.' : 'Could not save this row: '.Str::limit($e->getMessage(), 200)], $values);
        }
    }

    /**
     * Clean values from Excel / CSV before validation: trim, "" → null, Yes/No → bool,
     * Excel date numbers → dates, numbers in text columns → text.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function normalise(array $values, array $rules): array
    {
        foreach ($values as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;
            $columnRules = $rules[$key] ?? [];

            $value = match (true) {
                $value === '' || $value === null => null,
                $this->hasRule($columnRules, 'boolean') => $this->toBool($value),
                $this->hasRule($columnRules, 'date') && is_numeric($value) => ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s'),
                $this->hasRule($columnRules, 'string') && (is_int($value) || is_float($value)) => is_float($value) && floor($value) == $value ? (string) (int) $value : (string) $value,
                default => $value,
            };
            $values[$key] = $value;
        }

        return $values;
    }

    private function hasRule(mixed $rules, string $name): bool
    {
        $rules = is_string($rules) ? explode('|', $rules) : (array) $rules;

        return in_array($name, $rules, true);
    }

    private function toBool(mixed $value): bool|string
    {
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string) $value));

        return match (true) {
            in_array($v, ['1', 'yes', 'y', 'true', 'on', 'active'], true) => true,
            in_array($v, ['0', 'no', 'n', 'false', 'off', 'inactive'], true) => false,
            default => (string) $value, // let the "boolean" rule report it
        };
    }
}
