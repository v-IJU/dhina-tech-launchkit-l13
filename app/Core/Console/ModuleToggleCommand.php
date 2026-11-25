<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Menus\MenuService;
use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * php artisan module:enable Blog
 * php artisan module:disable Blog
 */
class ModuleToggleCommand extends Command
{
    protected $signature = 'module:enable {module : Module slug or folder name} {--disable : Disable instead}';

    protected $aliases = [];

    protected $description = 'Enable a module (use module:disable to switch one off)';

    public function handle(ModuleManager $modules, MenuService $menus): int
    {
        $name = strtolower((string) $this->argument('module'));
        $module = $modules->find($name) ?? $modules->all()->first(fn ($m) => strtolower(basename($m->path)) === $name);

        if (! $module) {
            $this->components->error("Module [{$this->argument('module')}] not found. Run module:list to see all modules.");

            return self::FAILURE;
        }

        $enable = ! $this->option('disable');

        try {
            $modules->setEnabled($module->slug, $enable);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $menus->flush();
        $this->call('route:clear');
        $this->components->info("Module [{$module->name}] ".($enable ? 'enabled' : 'disabled').'.');

        return self::SUCCESS;
    }
}
