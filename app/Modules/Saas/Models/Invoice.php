<?php

declare(strict_types=1);

namespace App\Modules\Saas\Models;

use App\Modules\Saas\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A bill for a tenant (created by a payment webhook, or by staff for manual payments).
 * Amounts in minor units. Numbers look like INV-2026-00042 (Settings > Billing prefix).
 *
 * @property int $id
 * @property string $number
 * @property string $tenant_id
 * @property int|null $subscription_id
 * @property int|null $plan_id
 * @property string $description
 * @property int $amount
 * @property string $tax_rate
 * @property int $tax
 * @property int $total
 * @property string $currency
 * @property string $status
 * @property string|null $gateway
 * @property string|null $gateway_payment_id
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property array<string, string|null>|null $billing
 * @property Carbon|null $created_at
 * @property-read Tenant $tenant
 * @property-read Plan|null $plan
 */
class Invoice extends Model
{
    use CentralConnection;

    public const STATUSES = ['open' => 'Unpaid', 'paid' => 'Paid', 'void' => 'Void'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'billing' => 'array',
            'amount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
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

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', 'paid');
    }

    public function money(string $field = 'total'): string
    {
        return Money::format((int) $this->getAttribute($field), $this->currency);
    }
}
