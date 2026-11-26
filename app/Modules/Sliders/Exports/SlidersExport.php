<?php

declare(strict_types=1);

namespace App\Modules\Sliders\Exports;

use App\Core\Export\ModuleExport;
use App\Modules\Sliders\Models\Slider;

/**
 * Sliders list → Export (Excel / CSV), with the list's current search and filters.
 * Same headings as SlidersImport, so an exported file can be edited and imported back.
 */
class SlidersExport extends ModuleExport
{
    public function headings(): array
    {
        return ['ID', 'Title', 'Subtitle', 'Button text', 'Button url', 'Sort order', 'Is active', 'Created'];
    }

    /** @param  Slider  $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->title,
            $row->subtitle,
            $row->button_text,
            $row->button_url,
            $row->sort_order,
            $this->yesNo($row->is_active),
            $this->date($row->created_at),
        ];
    }
}
