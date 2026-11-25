<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Deletes forever the rows that have been in a module's trash for longer than
 * Settings > Maintenance > "Empty the trash after (days)". Runs daily from the scheduler.
 * Users (app/Models) are never touched.
 */
class EmptyTrashCommand extends Command
{
    protected $signature = 'trash:empty {--days= : Override the setting} {--dry-run : Only count}';

    protected $description = 'Permanently delete module records that have been in the trash too long';

    public function handle(ModuleManager $modules): int
    {
        $days = (int) ($this->option('days') ?? setting('trash_days', 30));
        if ($days <= 0) {
            $this->components->info('Trash retention is set to "keep forever". Nothing to do.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $total = 0;

        foreach ($modules->all() as $module) {
            foreach (glob($module->file('Models/*.php')) ?: [] as $file) {
                $class = $module->namespace().'\\Models\\'.basename($file, '.php');
                if (! class_exists($class) || ! is_subclass_of($class, Model::class)
                    || ! in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                    continue;
                }

                $query = $class::query()->onlyTrashed()->where('deleted_at', '<=', $cutoff); // @phpstan-ignore method.notFound
                $count = $query->count();
                if ($count && ! $this->option('dry-run')) {
                    // one by one so model events run (e.g. uploaded files are removed)
                    $query->each(fn (Model $row) => $row->forceDelete());
                }
                if ($count) {
                    $this->line("  {$class}: {$count}");
                }
                $total += $count;
            }
        }

        $this->components->info(($this->option('dry-run') ? 'Would delete' : 'Deleted')." {$total} record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
