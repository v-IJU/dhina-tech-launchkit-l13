<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie role with the extra columns shown on the Roles page.
 *
 * @property int $id
 * @property string $name
 * @property string|null $display_name
 * @property string|null $description
 * @property string $color
 */
class Role extends SpatieRole
{
    public function label(): string
    {
        return $this->display_name ?: $this->name;
    }
}
