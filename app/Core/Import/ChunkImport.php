<?php

declare(strict_types=1);

namespace App\Core\Import;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\ToCollection;
use Throwable;

/**
 * Chunk import (Laravel Excel ToCollection): you get a whole chunk of rows (500, 1000…) at once and
 * write your own bulk queries: match names against other tables, whereIn(), one update for 500 rows,
 * insertOrIgnore(), upsert()… Use it for big or cross-table work. For normal CRUD use ModuleImport.
 *
 *   class FloristTagsImport extends ChunkImport
 *   {
 *       public function columns(): array { return ['product_name' => 'Red Rose Bouquet']; }
 *
 *       public function importChunk(Collection $rows): void
 *       {
 *           foreach ($rows as $line => $row) { … $this->skip($line, 'Not found', $row); }
 *           DB::table('products')->whereIn('name', $names)->update([...]);
 *           $this->updated(count($names));
 *       }
 *   }
 *
 * You count with $this->created($n) / $this->updated($n) and skip rows with $this->skip($line, 'reason', $row).
 * Progress, the result page, "Skipped rows" download, notifications, "only check" and background
 * running for big files all come from the base, the same as ModuleImport.
 */
abstract class ChunkImport extends BaseImport implements ToCollection
{
    use RemembersChunkOffset;

    /**
     * One chunk of rows, keyed by the real row number in the file (2, 3, 4… row 1 = headings).
     * Values are trimmed; empty cells are null. A thrown exception skips the whole chunk (listed as one error).
     *
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    abstract public function importChunk(Collection $rows): void;

    /** @param  Collection<int, Collection<string, mixed>>  $rows */
    public function collection(Collection $rows): void
    {
        // Real row numbers: the chunk starts at getChunkOffset() (the first data row of this chunk).
        // Empty rows are dropped only after numbering, so the numbers match what Excel shows.
        $start = $this->getChunkOffset() ?? 2;
        $this->processed($rows->count());
        $rows = $rows->values()->mapWithKeys(fn ($row, $i) => [
            $start + $i => collect($row)->filter(fn ($v, $k) => is_string($k) && $k !== '')
                ->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v),
        ])->reject(fn (Collection $row) => $row->filter(fn ($v) => $v !== null)->isEmpty());

        if ($rows->isEmpty()) {
            return;
        }

        // The whole chunk in one savepoint. "Only check": run everything, then undo it.
        DB::beginTransaction();
        try {
            $this->importChunk($rows);
            $this->dryRun() ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            $first = (int) $rows->keys()->first();
            $this->skip($first, 'Rows '.$first.'–'.$rows->keys()->last().' were not imported: '.(app()->isProduction() ? 'unexpected error.' : Str::limit($e->getMessage(), 200)));
        }
    }
}
