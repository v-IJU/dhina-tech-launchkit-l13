<?php

namespace App\Providers;

use App\Core\Tenancy\Tenancy;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Queue dashboard at /{admin_path}/horizon, for super admins only.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function register(): void
    {
        config([
            'horizon.path' => config('starter.horizon_path') ?: config('starter.admin_path').'/horizon',
            'horizon.middleware' => ['web', 'auth', 'active'],
        ]);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        if ($email = config('starter.horizon_alert_email')) {
            Horizon::routeMailNotificationsTo($email);
        }
    }

    protected function gate(): void
    {
        // Shared infrastructure: never inside a tenant (SaaS mode), only your own super admins.
        Gate::define('viewHorizon', fn (?User $user = null) => (bool) $user?->isSuperAdmin() && ! Tenancy::inTenant());
    }
}
