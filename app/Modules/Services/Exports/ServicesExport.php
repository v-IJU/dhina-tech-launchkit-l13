<?php

declare(strict_types=1);

namespace App\Modules\Services\Exports;

use App\Core\Export\ModuleExport;
use App\Modules\Services\Models\Service;

/**
 * Services list → Export (Excel / CSV), with the list's current search and filters.
 * Same headings as ServicesImport, so an exported file can be edited and imported back.
 */
class ServicesExport extends ModuleExport
{
    public function headings(): array
    {
        return ['ID', 'Title', 'Icon', 'Summary', 'Sort order', 'Is active', 'Created'];
    }

    /** @param  Service  $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->title,
            $row->icon,
            $row->summary,
            $row->sort_order,
            $this->yesNo($row->is_active),
            $this->date($row->created_at),
        ];
    }
}
