<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A queue job that failed after all its tries (Laravel's failed_jobs table).
 *
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property Carbon $failed_at
 */
class FailedJob extends Model
{
    public $timestamps = false;

    protected $table = 'failed_jobs';

    protected function casts(): array
    {
        return ['failed_at' => 'datetime'];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadData(): array
    {
        return json_decode($this->payload, true) ?: [];
    }

    /**
     * "App\Notifications\AdminAlert" or "App\Modules\Backups\Jobs\RunBackup".
     */
    public function jobName(): string
    {
        return (string) ($this->payloadData()['displayName'] ?? 'Unknown job');
    }

    /**
     * First line of the exception, e.g. "Swift_TransportException: Connection refused".
     */
    public function errorSummary(): string
    {
        return Str::limit(Str::before($this->exception, "\n"), 200);
    }

    public function attempts(): int
    {
        return (int) ($this->payloadData()['attempts'] ?? 0);
    }
}
