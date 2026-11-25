<?php

declare(strict_types=1);

namespace App\Core\Console;

use Illuminate\Console\Command;

class ModuleDisableCommand extends Command
{
    protected $signature = 'module:disable {module : Module slug or folder name}';

    protected $description = 'Disable a module (hides its menus and routes, keeps its data)';

    public function handle(): int
    {
        return $this->call('module:enable', ['module' => $this->argument('module'), '--disable' => true]);
    }
}
