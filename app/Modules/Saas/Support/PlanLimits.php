<?php

declare(strict_types=1);

namespace App\Modules\Saas\Support;

use App\Core\Tenancy\Tenancy;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\TenantUsage;
use Illuminate\Validation\ValidationException;

/**
 * Plan limits inside a tenant (SaaS mode). Single-business mode: everything unlimited.
 *
 *   PlanLimits::usage()            ['users' => [3, 5], 'customers' => [120, null], 'storage_mb' => [210, 1024]]
 *   PlanLimits::ensureCanAdd('users')   throws a validation error when the limit is reached
 *
 * Users and customers are checked when they are created (Billing module listens to "creating").
 * Storage is measured nightly (saas:collect-usage) and shown on the Billing page.
 */
final class PlanLimits
{
    /**
     * @return array<string, array{0: int, 1: int|null}> used, limit (null = unlimited)
     */
    public static function usage(): array
    {
        $tenant = Tenancy::tenant();
        if (! $tenant) {
            return [];
        }

        $storage = (int) Tenancy::central(fn () => TenantUsage::query()->where('tenant_id', $tenant->id)->latest('date')->value('storage_mb'));

        return [
            'users' => [self::count('users'), $tenant->limit('users')],
            'customers' => [self::count('customers'), $tenant->limit('customers')],
            'storage_mb' => [$storage, $tenant->limit('storage_mb')],
        ];
    }

    public static function ensureCanAdd(string $key): void
    {
        $tenant = Tenancy::tenant();
        $limit = $tenant?->limit($key);

        if ($limit === null || self::count($key) < $limit) {
            return;
        }

        $label = strtolower(Plan::LIMITS[$key] ?? $key);

        throw ValidationException::withMessages([
            'email' => "Your plan allows {$limit} {$label}. Upgrade under Billing to add more.",
        ]);
    }

    private static function count(string $key): int
    {
        return match ($key) {
            'users' => User::query()->count(),
            'customers' => class_exists(Customer::class) ? Customer::query()->count() : 0,
            default => 0,
        };
    }
}
