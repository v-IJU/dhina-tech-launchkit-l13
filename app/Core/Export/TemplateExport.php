<?php

declare(strict_types=1);

namespace App\Core\Export;

use App\Core\Import\BaseImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * "Download template": the import's headings and one example row.
 *   Excel::download(new TemplateExport(new FaqsImport), 'faqs-template.xlsx')
 */
class TemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private BaseImport $import) {}

    /** @return list<string> */
    public function headings(): array
    {
        return array_map(fn ($c) => BaseImport::heading((string) $c), array_keys($this->import->columns()));
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        $example = array_values($this->import->columns());

        return array_filter($example, fn ($v) => $v !== '') ? [$example] : [];
    }
}
