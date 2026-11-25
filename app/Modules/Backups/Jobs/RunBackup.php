<?php

declare(strict_types=1);

namespace App\Modules\Backups\Jobs;

use App\Notifications\AdminAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs spatie/laravel-backup on the queue so the admin page stays fast.
 */
class RunBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public bool $databaseOnly = false) {}

    public function handle(): void
    {
        $exit = Artisan::call('backup:run', array_filter(['--only-db' => $this->databaseOnly, '--disable-notifications' => true]));

        AdminAlert::toAdmins(
            $exit === 0 ? 'Backup finished successfully' : 'Backup failed — check storage/logs',
            route('admin.backups.index'),
            'backups.view',
            $exit === 0 ? 'bi-database-check' : 'bi-exclamation-triangle',
        );
    }
}
