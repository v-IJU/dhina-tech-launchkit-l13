<?php

declare(strict_types=1);

namespace App\Core\Permissions;

use App\Core\Modules\ModuleManager;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permissions declared in every module.json.
 * Never deletes anything, so custom permissions made in the UI are kept.
 */
class PermissionSync
{
    public function __construct(private readonly ModuleManager $modules) {}

    /**
     * @return int number of permissions created
     */
    public function run(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(config('starter.super_admin_role'), 'web');

        $created = 0;
        foreach ($this->modules->all() as $module) {
            foreach ($module->permissionNames() as $name) {
                if (! Permission::query()->where('name', $name)->where('guard_name', 'web')->exists()) {
                    Permission::create(['name' => $name, 'guard_name' => 'web']);
                    $created++;
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }
}
