<?php

declare(strict_types=1);

namespace App\Modules\Users\Exports;

use App\Core\Export\ModuleExport;
use App\Models\User;

/**
 * Users list → Export (Excel / CSV), with the list's current search and filters.
 * Same headings as UsersImport, so an exported file can be edited and imported back.
 */
class UsersExport extends ModuleExport
{
    public function headings(): array
    {
        return ['ID', 'First name', 'Last name', 'Email', 'Username', 'Phone', 'Role', 'Status', 'Department', 'Job title', 'Last login', 'Joined'];
    }

    /** @param  User  $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->first_name,
            $row->last_name,
            $row->email,
            $row->username,
            $row->phone,
            $row->getRoleNames()->join(', '),
            $row->status,
            $row->department,
            $row->job_title,
            $this->date($row->last_login_at),
            $this->date($row->created_at),
        ];
    }
}
