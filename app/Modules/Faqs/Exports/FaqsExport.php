<?php

declare(strict_types=1);

namespace App\Modules\Faqs\Exports;

use App\Core\Export\ModuleExport;
use App\Modules\Faqs\Models\Faq;

/**
 * FAQs list → Export (Excel / CSV), with the list's current search and filters.
 * Same headings as FaqsImport, so an exported file can be edited and imported back.
 */
class FaqsExport extends ModuleExport
{
    public function headings(): array
    {
        return ['ID', 'Question', 'Answer', 'Group name', 'Sort order', 'Is active', 'Created'];
    }

    /** @param  Faq  $faq */
    public function map($faq): array
    {
        return [
            $faq->id,
            $faq->question,
            $faq->answer,
            $faq->group_name,
            $faq->sort_order,
            $this->yesNo($faq->is_active),
            $this->date($faq->created_at),
        ];
    }
}
