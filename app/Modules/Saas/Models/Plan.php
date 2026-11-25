<?php

declare(strict_types=1);

namespace App\Modules\Saas\Models;

use App\Modules\Saas\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A subscription plan (SaaS console > Plans). Prices are in minor units (49900 = ₹499.00).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_monthly
 * @property int $price_yearly
 * @property string $currency
 * @property int $trial_days
 * @property array<int, string>|null $modules
 * @property array<string, int|null>|null $limits
 * @property array<int, string>|null $features
 * @property string|null $razorpay_plan_monthly
 * @property string|null $razorpay_plan_yearly
 * @property string|null $stripe_price_monthly
 * @property string|null $stripe_price_yearly
 * @property bool $is_public
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 */
class Plan extends Model
{
    use CentralConnection;

    /** Limits a plan can set. Empty = unlimited. */
    public const LIMITS = [
        'users' => 'Staff users',
        'customers' => 'Customer accounts',
        'storage_mb' => 'File storage (MB)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'limits' => 'array',
            'features' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'trial_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('price_monthly');
    }

    public function limit(string $key): ?int
    {
        $value = ($this->limits ?? [])[$key] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function price(string $interval): int
    {
        return $interval === 'year' ? $this->price_yearly : $this->price_monthly;
    }

    public function isFree(): bool
    {
        return $this->price_monthly === 0 && $this->price_yearly === 0;
    }

    public function formattedPrice(string $interval = 'month'): string
    {
        return Money::format($this->price($interval), $this->currency);
    }

    /**
     * Gateway plan / price id for checkout, e.g. Razorpay "plan_N8x…" or Stripe "price_1P…".
     */
    public function gatewayId(string $gateway, string $interval): ?string
    {
        $column = $gateway.'_'.($gateway === 'stripe' ? 'price' : 'plan').'_'.($interval === 'year' ? 'yearly' : 'monthly');

        return $this->getAttribute($column) ?: null;
    }

    /**
     * Monthly value, for MRR.
     */
    public function monthlyValue(string $interval): int
    {
        return $interval === 'year' ? (int) round($this->price_yearly / 12) : $this->price_monthly;
    }
}
