<?php

declare(strict_types=1);

namespace App\Modules\Customers\Exports;

use App\Core\Export\ModuleExport;
use App\Modules\Customers\Models\Customer;

/**
 * Customers list → Export (Excel / CSV), with the list's current search and filters.
 * Same headings as CustomersImport, so an exported file can be edited and imported back.
 */
class CustomersExport extends ModuleExport
{
    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Phone', 'Status', 'Email confirmed', '2FA', 'Marketing opt in', 'Addresses', 'Last login', 'Joined'];
    }

    /** @param  Customer  $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->name,
            $row->email,
            $row->phone,
            $row->status,
            $this->yesNo($row->email_verified_at),
            $row->two_factor_confirmed_at ? 'On' : 'Off',
            $this->yesNo($row->marketing_opt_in),
            $row->addresses_count,
            $this->date($row->last_login_at),
            $this->date($row->created_at),
        ];
    }
}
