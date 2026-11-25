<?php

declare(strict_types=1);

namespace App\Modules\Saas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A tenant's paid subscription on Razorpay, Stripe, or "manual" (bank transfer, recorded by staff).
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $plan_id
 * @property string $gateway
 * @property string $interval
 * @property string $status
 * @property string|null $gateway_id
 * @property string|null $gateway_customer_id
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property bool $cancel_at_period_end
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property-read Tenant $tenant
 * @property-read Plan|null $plan
 */
class Subscription extends Model
{
    use CentralConnection;

    public const STATUSES = [
        'pending' => 'Waiting for payment',
        'active' => 'Active',
        'past_due' => 'Payment failed',
        'cancelled' => 'Cancelled (runs to period end)',
        'ended' => 'Ended',
    ];

    public const GATEWAYS = ['razorpay' => 'Razorpay', 'stripe' => 'Stripe', 'manual' => 'Manual / bank transfer'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['active', 'past_due', 'cancelled'], true)
            && (! $this->current_period_end || $this->current_period_end->isFuture() || $this->status === 'past_due');
    }
}
