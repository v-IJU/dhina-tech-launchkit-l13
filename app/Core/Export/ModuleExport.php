<?php

declare(strict_types=1);

namespace App\Core\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Base for a module's export (Laravel Excel / Maatwebsite). A module export only says:
 *
 *   public function headings(): array        // ['ID', 'Question', …]  (same as the import headings)
 *   public function map($row): array         // one model → one spreadsheet row
 *
 * The controller passes the list's query, so the file has exactly what the list shows
 * (search, filters, sorting):  Excel::download(new FaqsExport($table->query()), 'faqs.xlsx')
 *
 * Rows are read from the database in chunks (FromQuery), so big tables don't fill the memory.
 * Text that starts with = + - @ is written as plain text, never as a formula.
 */
abstract class ModuleExport extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(protected Builder $query) {}

    /** @return Builder<covariant Model> */
    public function query(): Builder
    {
        return $this->query;
    }

    /** @return list<string> */
    abstract public function headings(): array;

    /**
     * One row of the query → one line of the file. Type $row in your class: @param  Faq  $row
     *
     * @param  Model  $row
     * @return list<mixed>
     */
    abstract public function map($row): array;

    /** Helpers for map(): Yes/No and dates the same way everywhere. */
    protected function yesNo(mixed $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    protected function date(?\DateTimeInterface $value): string
    {
        return $value?->format('Y-m-d H:i:s') ?? '';
    }

    /** Set by the list page when the user picked CSV (spreadsheet apps run =… in CSV cells). */
    public bool $csv = false;

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            // Never a formula from user data: an explicit text cell in Excel, a leading ' in CSV.
            $cell->setValueExplicit($this->csv ? "'".$value : $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
