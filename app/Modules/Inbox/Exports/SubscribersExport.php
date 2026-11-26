<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Exports;

use App\Core\Export\ModuleExport;
use App\Modules\Inbox\Models\Subscriber;

/**
 * Subscribers list → Export (Excel / CSV), with the list's current search and filters.
 * Same headings as SubscribersImport, so an exported file can be edited and imported back.
 */
class SubscribersExport extends ModuleExport
{
    public function headings(): array
    {
        return ['Email', 'Subscribed at'];
    }

    /** @param  Subscriber  $row */
    public function map($row): array
    {
        return [
            $row->email,
            $this->date($row->created_at),
        ];
    }
}
