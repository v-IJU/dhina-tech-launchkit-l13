<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Modules\Module;
use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List all modules and whether they are enabled';

    public function handle(ModuleManager $modules): int
    {
        $this->table(
            ['Module', 'Slug', 'Version', 'Type', 'Status', 'Path'],
            $modules->all()->map(fn (Module $m) => [
                $m->name,
                $m->slug,
                $m->version,
                $m->core ? 'core' : 'custom',
                $m->enabled ? '<fg=green>enabled</>' : '<fg=yellow>disabled</>',
                str_replace(base_path().'/', '', $m->path),
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
