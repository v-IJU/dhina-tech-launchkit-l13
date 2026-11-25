<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One distinct error seen by the app. Written by App\Core\Monitoring\ErrorRecorder.
 *
 * @property int $id
 * @property string $fingerprint
 * @property string|null $tenant_id
 * @property string $class
 * @property string $message
 * @property string|null $file
 * @property int|null $line
 * @property string|null $trace
 * @property string|null $url
 * @property string|null $method
 * @property string|null $user_type
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property int $occurrences
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $resolved_at
 */
class ErrorLog extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * SaaS mode: always the central database, so you see every tenant's errors in one place.
     */
    public function getConnectionName(): ?string
    {
        return Tenancy::enabled() ? config('tenancy.database.central_connection') : parent::getConnectionName();
    }

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'occurrences' => 'integer',
            'line' => 'integer',
        ];
    }

    /**
     * Not seen for longer than Settings > Monitoring > "Keep errors for (days)". Run by model:prune daily.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('last_seen_at', '<', now()->subDays(max(1, (int) setting('error_log_days', 30))));
    }

    /**
     * @param  Builder<ErrorLog>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    public function shortClass(): string
    {
        return class_basename($this->class);
    }

    /**
     * "app/Modules/Blog/Controllers/PostController.php:42"
     */
    public function location(): string
    {
        return $this->file ? $this->file.($this->line ? ':'.$this->line : '') : '—';
    }
}
