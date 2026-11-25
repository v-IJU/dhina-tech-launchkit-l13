<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Menus\MenuService;
use App\Core\Permissions\PermissionSync;
use Illuminate\Console\Command;

/**
 * Reads every module.json and creates missing permissions and sidebar items.
 * Safe to run any time; it never removes custom permissions or menu changes.
 */
class ModuleSyncCommand extends Command
{
    protected $signature = 'module:sync';

    protected $aliases = ['module:sync-permissions'];

    protected $description = 'Create permissions and sidebar menu items declared in module.json files';

    public function handle(PermissionSync $permissions, MenuService $menus): int
    {
        $p = $permissions->run();
        $m = $menus->syncFromModules();

        $this->components->info("Synced: {$p} new permission(s), {$m} new menu item(s).");

        return self::SUCCESS;
    }
}
