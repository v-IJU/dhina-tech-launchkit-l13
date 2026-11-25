<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Core\Tenancy\Tenancy;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Saas\Support\PlanLimits;
use Illuminate\Support\ServiceProvider;

/**
 * Plan limits inside a tenant: adding a staff user or a customer beyond the plan's limit fails with
 * "Your plan allows 5 staff users. Upgrade under Billing". Single-business mode: nothing happens.
 */
class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::creating(function () {
            if (Tenancy::inTenant()) {
                PlanLimits::ensureCanAdd('users');
            }
        });

        if (class_exists(Customer::class)) {
            Customer::creating(function () {
                if (Tenancy::inTenant()) {
                    PlanLimits::ensureCanAdd('customers');
                }
            });
        }
    }
}
