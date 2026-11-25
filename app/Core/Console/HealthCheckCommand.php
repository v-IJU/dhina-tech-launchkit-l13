<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Health\HealthCheck;
use Illuminate\Console\Command;

/**
 * php artisan health:check    → table of every check; exit code 1 when a critical one fails.
 * (deploy.sh status runs it; /up uses the critical checks only.)
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'health:check';

    protected $description = 'Check the database, cache, storage, Redis, queue workers, scheduler and disk space';

    public function handle(): int
    {
        $checks = HealthCheck::run();

        $this->table(['Check', 'Status', 'Details'], array_map(fn (array $c) => [
            $c['name'].($c['critical'] ? '' : ' (warning only)'),
            $c['ok'] ? '<fg=green>OK</>' : ($c['critical'] ? '<fg=red>FAIL</>' : '<fg=yellow>WARN</>'),
            $c['detail'],
        ], $checks));

        return collect($checks)->contains(fn (array $c) => $c['critical'] && ! $c['ok']) ? self::FAILURE : self::SUCCESS;
    }
}
