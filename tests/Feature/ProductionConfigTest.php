<?php

namespace Tests\Feature;

use App\Core\Import\ImportRun;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Tests\TestCase;

/**
 * Production settings that protect a live site.
 */
class ProductionConfigTest extends TestCase
{
    public function test_backups_dump_mysql_without_locking_tables(): void
    {
        $dumper = DbDumperFactory::createFromConnection('mysql');
        $command = $dumper->getDumpCommand('/tmp/dump.sql', '/tmp/credentials.txt');

        $this->assertStringContainsString('--single-transaction', $command);   // consistent snapshot, no locks
        $this->assertStringContainsString('--skip-lock-tables', $command);
        $this->assertStringContainsString('--quick', $command);                // stream rows, low memory
    }

    public function test_imports_wait_reports_running_imports_and_ignores_old_stuck_ones(): void
    {
        $this->artisan('imports:wait', ['--timeout' => 0])->expectsOutput('No background import running.')->assertSuccessful();

        ImportRun::query()->create(['module' => 'faqs', 'file_name' => 'big.xlsx', 'path' => 'x', 'status' => 'running', 'queued' => true, 'total_rows' => 1000, 'processed_rows' => 250]);
        $stuck = ImportRun::query()->create(['module' => 'faqs', 'file_name' => 'old.xlsx', 'path' => 'y', 'status' => 'running', 'queued' => true]);
        $stuck->forceFill(['created_at' => now()->subHours(3)])->save();

        $this->artisan('imports:wait', ['--timeout' => 0])
            ->expectsOutputToContain('big.xlsx): 25% (250 / 1,000 rows)')
            ->doesntExpectOutputToContain('old.xlsx')
            ->expectsOutputToContain('continuing')
            ->assertSuccessful();
    }

    public function test_horizon_worker_count_comes_from_env(): void
    {
        $this->assertSame(5, config('horizon.environments.production.supervisor-1.maxProcesses'));
        $this->assertSame(3600, config('horizon.defaults.supervisor-imports.timeout'));
    }
}
