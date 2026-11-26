<?php

declare(strict_types=1);

namespace App\Modules\Ai\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $channel assistant|mcp
 * @property string|null $provider
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property array<int, string>|null $tools
 * @property string $status ok|error|denied|limit
 * @property string|null $message
 * @property int $duration_ms
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class AiUsageLog extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'channel', 'provider', 'model', 'input_tokens', 'output_tokens', 'tools', 'status', 'message', 'duration_ms'];

    protected function casts(): array
    {
        return ['tools' => 'array', 'input_tokens' => 'integer', 'output_tokens' => 'integer', 'duration_ms' => 'integer', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeThisMonth(Builder $query): void
    {
        $query->where('created_at', '>=', now()->startOfMonth());
    }

    public function totalTokens(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }

    /** Kept for a year (php artisan model:prune runs nightly). */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subYear());
    }
}
