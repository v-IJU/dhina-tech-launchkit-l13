<?php

declare(strict_types=1);

namespace App\Modules\Saas\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A customer of your SaaS. Lives in the CENTRAL database; its own data is in its own database.
 *
 * Real columns: id, name, email, plan_id, status, trial_ends_at, suspended_at.
 * Anything else ($tenant->gstin, ->owner_name, ->extra_modules, ->limit_overrides, ->notes…)
 * is stored in the JSON "data" column automatically.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property int|null $plan_id
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $created_at
 * @property string|null $gstin
 * @property string|null $billing_address
 * @property string|null $notes
 * @property array<int, string>|null $extra_modules
 * @property array<string, int>|null $limit_overrides
 * @property string|null $owner_name
 * @property string|null $owner_email
 * @property string|null $owner_password
 * @property bool|null $with_demo
 * @property bool|null $converted
 * @property-read Plan|null $plan
 * @property-read Subscription|null $currentSubscription
 * @property-read Collection<int, Domain> $domains
 *
 * @method static Builder<static> query()
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public const STATUSES = [
        'trial' => 'Trial',
        'active' => 'Active',
        'past_due' => 'Payment due',
        'suspended' => 'Suspended',
        'cancelled' => 'Cancelled',
    ];

    /** Always needed (URL, modules, limits), and stancl's commands load tenants with cursor(). */
    protected $with = ['domains', 'plan'];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /**
     * stancl's commands (tenants:list, tenants:run…) walk tenants with cursor(), which can't eager load.
     * Loading these two on demand is fine there; anything else still reports lazy loading.
     *
     * @param  string  $key
     */
    protected function handleLazyLoadingViolation($key): mixed
    {
        if (in_array($key, ['domains', 'plan'], true)) {
            return null;
        }

        return parent::handleLazyLoadingViolation($key);
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id');
    }

    /**
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'email', 'plan_id', 'status', 'trial_ends_at', 'suspended_at', 'created_at', 'updated_at'];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest('id');
    }

    /**
     * The subscription that counts now (active, past due or cancelled-but-still-running).
     *
     * @return HasOne<Subscription, $this>
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->whereIn('status', ['active', 'past_due', 'cancelled'])->latestOfMany();
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('id');
    }

    /**
     * @return HasMany<TenantUsage, $this>
     */
    public function usage(): HasMany
    {
        return $this->hasMany(TenantUsage::class)->latest('date');
    }

    /**
     * Modules this tenant can use besides the core ones: its plan's modules + any extras staff added.
     *
     * @return array<int, string>
     */
    public function moduleSlugs(): array
    {
        return array_values(array_unique([...($this->plan->modules ?? []), ...((array) ($this->extra_modules ?? []))]));
    }

    /**
     * A plan limit (users, customers, storage_mb), with this tenant's own override if staff set one.
     * Null = unlimited.
     */
    public function limit(string $key): ?int
    {
        $override = ((array) ($this->limit_overrides ?? []))[$key] ?? null;
        if ($override !== null && $override !== '') {
            return (int) $override;
        }

        return $this->plan?->limit($key);
    }

    /**
     * Blocked = only sign-in and Billing work (IdentifyTenant).
     */
    public function isBlocked(): bool
    {
        return in_array($this->status, ['suspended', 'cancelled'], true);
    }

    public function onTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public function trialDaysLeft(): int
    {
        return $this->trial_ends_at ? max(0, (int) ceil(now()->diffInHours($this->trial_ends_at, false) / 24)) : 0;
    }

    /**
     * "acme.yourapp.com" (first subdomain), or the first custom domain.
     */
    public function primaryDomain(): string
    {
        $domain = $this->domains->sortBy('id')->first()->domain ?? $this->id;

        return str_contains((string) $domain, '.') ? (string) $domain : $domain.'.'.config('tenancy.central_domains.0');
    }

    public function url(string $path = ''): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return $scheme.'://'.$this->primaryDomain().($port ? ':'.$port : '').'/'.ltrim($path, '/');
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('id', 'like', "%{$term}%")
            ->orWhereHas('domains', fn (Builder $d) => $d->where('domain', 'like', "%{$term}%")));
    }
}
