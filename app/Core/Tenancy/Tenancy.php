<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use App\Modules\Saas\Models\Tenant;

/**
 * One place to ask "which mode / which context am I in?".
 *
 *   Tenancy::enabled()   SaaS mode on (STARTER_TENANCY=true)
 *   Tenancy::tenant()    the current tenant, or null
 *   Tenancy::inTenant()  a tenant's panel / website / API
 *   Tenancy::inCentral() SaaS mode, central app (your console, pricing, signup)
 *
 * In single-business mode enabled() is false and the rest are null/false.
 */
final class Tenancy
{
    public static function enabled(): bool
    {
        return (bool) config('starter.tenancy.enabled');
    }

    public static function tenant(): ?Tenant
    {
        if (! self::enabled() || ! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public static function inTenant(): bool
    {
        return self::tenant() !== null;
    }

    public static function inCentral(): bool
    {
        return self::enabled() && ! self::inTenant();
    }

    public static function centralDomain(): string
    {
        return (string) (config('tenancy.central_domains')[0] ?? 'localhost');
    }

    public static function isCentralHost(string $host): bool
    {
        return in_array(strtolower($host), array_map('strtolower', config('tenancy.central_domains', [])), true);
    }

    /**
     * Run code in the central database (plans, tenants, invoices), from anywhere.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function central(callable $callback): mixed
    {
        return self::inTenant() ? tenancy()->central($callback) : $callback();
    }

    /**
     * Channel prefix so tenant A's user 1 and tenant B's user 1 never share a Reverb channel.
     */
    public static function channelPrefix(): string
    {
        $tenant = self::tenant();

        return $tenant ? 'tenant.'.$tenant->getTenantKey().'.' : '';
    }
}
