<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Import\ImportRun;
use Illuminate\Console\Command;

/**
 * Used by deploy.sh before it restarts the workers: a big import running in the background
 * (up to an hour) would otherwise be cut off halfway by the restart.
 *
 *   php artisan imports:wait              wait up to 15 minutes, then continue anyway
 *   php artisan imports:wait --timeout=0  only report, don't wait
 */
class ImportsWaitCommand extends Command
{
    protected $signature = 'imports:wait {--timeout=900 : seconds to wait at most}';

    protected $description = 'Wait until no background import is running (deploys call this before restarting workers)';

    public function handle(): int
    {
        $deadline = time() + max(0, (int) $this->option('timeout'));

        while (true) {
            $running = $this->running();
            if ($running === []) {
                $this->info('No background import running.');

                return self::SUCCESS;
            }

            $this->line(collect($running)->map(fn (ImportRun $run) => sprintf(
                '  Import #%d (%s, %s): %s%% (%s / %s rows)',
                $run->id, $run->module, $run->file_name, $run->percent(),
                number_format($run->processed_rows), number_format($run->total_rows),
            ))->implode("\n"));

            if (time() >= $deadline) {
                $this->warn('Still running after the wait: continuing. A restarted worker stops it; it will show as failed.');

                return self::SUCCESS;
            }

            $this->line('  Waiting for it to finish before restarting the workers…');
            sleep(10);
        }
    }

    /** @return list<ImportRun> */
    private function running(): array
    {
        // Background imports that really are running (started within the job's 1-hour limit).
        return ImportRun::query()
            ->where('queued', true)
            ->whereIn('status', ['pending', 'running'])
            ->where('created_at', '>=', now()->subMinutes(70))
            ->get()->all();
    }
}
