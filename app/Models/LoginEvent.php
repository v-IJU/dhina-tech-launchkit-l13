<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One sign-in, failed attempt, sign-out or lockout. Written by App\Core\Security\LoginRecorder.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $customer_id
 * @property string $guard
 * @property string|null $email
 * @property string $event
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $browser
 * @property string|null $platform
 * @property string|null $device_hash
 * @property bool $is_new_device
 * @property Carbon|null $created_at
 */
class LoginEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public const EVENTS = ['login' => 'Signed in', 'failed' => 'Failed sign-in', 'logout' => 'Signed out', 'lockout' => 'Locked out'];

    protected $fillable = ['user_id', 'customer_id', 'guard', 'email', 'event', 'ip_address', 'user_agent', 'browser', 'platform', 'device_hash', 'is_new_device'];

    protected function casts(): array
    {
        return ['is_new_device' => 'boolean', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * Staff events only (admin panel and staff API).
     *
     * @param  Builder<LoginEvent>  $query
     */
    public function scopeStaff(Builder $query): void
    {
        $query->where('guard', 'web');
    }

    /**
     * Customer events only (website and shop API), including failed attempts for unknown emails.
     *
     * @param  Builder<LoginEvent>  $query
     */
    public function scopeCustomers(Builder $query): void
    {
        $query->where('guard', 'customer');
    }

    /**
     * Removed by "php artisan model:prune" (scheduled daily).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) setting('login_history_days', 180)));
    }

    public function device(): string
    {
        return ($this->browser ?: 'Unknown').' on '.($this->platform ?: 'Unknown OS');
    }
}
