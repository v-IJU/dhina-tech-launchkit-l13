<?php

declare(strict_types=1);

namespace App\Modules\Testimonials\Exports;

use App\Core\Export\ModuleExport;
use App\Modules\Testimonials\Models\Testimonial;

/**
 * Testimonials list → Export (Excel / CSV), with the list's current search and filters.
 * Same headings as TestimonialsImport, so an exported file can be edited and imported back.
 */
class TestimonialsExport extends ModuleExport
{
    public function headings(): array
    {
        return ['ID', 'Client name', 'Company', 'Rating', 'Quote', 'Is active', 'Created'];
    }

    /** @param  Testimonial  $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->client_name,
            $row->company,
            $row->rating,
            $row->quote,
            $this->yesNo($row->is_active),
            $this->date($row->created_at),
        ];
    }
}
