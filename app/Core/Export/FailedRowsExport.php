<?php

declare(strict_types=1);

namespace App\Core\Export;

use App\Core\Import\ImportRun;
use App\Core\Import\ImportRunError;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The rows an import skipped: the original columns + "Row" + "Error". Fix them in Excel and import
 * this same file again (the extra columns are ignored).
 */
class FailedRowsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @var list<string> */
    private array $columns;

    public function __construct(private ImportRun $run)
    {
        $first = $run->errors()->first();
        $this->columns = $first ? array_map('strval', array_keys($first->values)) : [];
    }

    public function query(): Builder
    {
        return $this->run->errors()->orderBy('row')->getQuery();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [...$this->columns, 'Row', 'Error'];
    }

    /**
     * @param  ImportRunError  $error
     * @return list<mixed>
     */
    public function map($error): array
    {
        return [...array_map(fn ($c) => $error->values[$c] ?? '', $this->columns), $error->row, implode(' ', $error->messages)];
    }
}
