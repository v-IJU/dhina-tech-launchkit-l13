<?php

declare(strict_types=1);

namespace App\Core\Import;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * A big import, as one job on the "imports" queue (config/starter.php → imports: SQS, Redis…).
 * Inside the job the file is read chunk by chunk (Settings → Import & export → rows per chunk):
 * each chunk is its own database transaction and updates the progress bar.
 *
 * One job per file, chunks in order: rows are never imported twice or out of order, and unique
 * columns (email, sku) can't clash between two chunks running at the same time on two workers.
 */
class RunImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Long files take time. Must stay below the queue's retry_after / SQS visibility timeout (3700 / 3600+). */
    public int $timeout = 3600;

    /** An import is never retried automatically (rows already saved would be saved again). */
    public int $tries = 1;

    public function __construct(public BaseImport $import, public int $runId)
    {
        // Own queue for long jobs (config/starter.php → imports). A module may pick another queue name.
        $this->onConnection(config('starter.imports.connection'));
        $this->onQueue($import->queue ?? config('starter.imports.queue'));
    }

    public function handle(): void
    {
        $run = ImportRun::query()->find($this->runId);
        if ($run === null) {
            return;
        }

        try {
            Excel::import($this->import, $run->path, ImportRun::DISK);
        } catch (Throwable $e) {
            $run->fail($e);
        }
    }

    public function failed(Throwable $e): void
    {
        ImportRun::query()->find($this->runId)?->fail($e);  // e.g. the job timed out
    }
}
