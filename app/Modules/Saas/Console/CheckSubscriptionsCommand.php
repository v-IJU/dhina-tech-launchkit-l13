<?php

declare(strict_types=1);

namespace App\Modules\Saas\Console;

use App\Modules\Saas\Billing\SubscriptionService;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Notifications\TenantBillingNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Hourly (routes/console.php): trial reminders, then suspend tenants whose trial or
 * grace period ran out, and end cancelled subscriptions after their paid period.
 */
class CheckSubscriptionsCommand extends Command
{
    protected $signature = 'saas:check-subscriptions';

    protected $description = 'SaaS: remind trials ending soon, suspend unpaid tenants, end finished subscriptions';

    public function handle(SubscriptionService $service): int
    {
        // "Your trial ends in 3 days", once per tenant.
        Tenant::query()->where('status', 'trial')->whereBetween('trial_ends_at', [now()->addDays(2), now()->addDays(3)])->lazyById()
            ->each(function (Tenant $tenant) {
                if (Cache::add("saas:trial-reminder:{$tenant->id}", true, now()->addDays(5))) {
                    Notification::route('mail', $tenant->email)->notify(new TenantBillingNotice($tenant, 'trial_ending'));
                }
            });

        $changed = $service->checkAll();
        $this->components->info("{$changed} tenant(s) changed.");

        return self::SUCCESS;
    }
}
