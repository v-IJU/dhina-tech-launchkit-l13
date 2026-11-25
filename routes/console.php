<?php

use App\Core\Tenancy\Tenancy;
use App\Models\ErrorLog;
use App\Models\LoginEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Run with "php artisan schedule:work" (the scheduler container does this for you).

/*
| Tasks that work on a tenant's own data. Single business: runs normally.
| SaaS mode: runs once for EVERY tenant database ("php artisan tenants:run …").
*/
$perTenant = function (string $command, array $options = []): Event {
    if (! Tenancy::enabled()) {
        return Schedule::command($command, $options);
    }

    $pairs = [];
    foreach ($options as $name => $value) {
        foreach ((array) $value as $one) {
            $pairs[] = ltrim((string) $name, '-').'='.$one;
        }
    }

    return Schedule::command('tenants:run', array_filter([$command, '--option' => $pairs]));
};

// Heartbeat shown on the dashboard's System status card.
Schedule::call(fn () => Cache::put('scheduler:last-run', now()->toIso8601String(), now()->addDay()))
    ->everyMinute()->name('scheduler-heartbeat');

// Blog: publish posts whose scheduled time has come.
$perTenant('blog:publish-scheduled')->everyMinute()->withoutOverlapping();

// Backups (spatie/laravel-backup): clean old ones, then make a new one.
// SaaS mode: this backs up the central database; tenant databases → RDS snapshots (SAAS.md).
Schedule::command('backup:clean')->dailyAt('01:30')->onOneServer();
Schedule::command('backup:run')->dailyAt('02:00')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('09:00')->onOneServer();

// Housekeeping.
$perTenant('activitylog:clean')->dailyAt('03:00')->onOneServer();
$perTenant('sitemap:generate')->dailyAt('03:15')->onOneServer();
$perTenant('trash:empty')->dailyAt('03:30')->onOneServer();
$perTenant('model:prune', ['--model' => [LoginEvent::class]])->dailyAt('03:45')->onOneServer();
Schedule::command('model:prune', ['--model' => [ErrorLog::class]])->dailyAt('03:50')->onOneServer();
$perTenant('sanctum:prune-expired', ['--hours' => 24])->dailyAt('04:00')->onOneServer(); // expired app/API tokens
Schedule::command('queue:prune-failed --hours=168')->daily()->onOneServer();
$perTenant('auth:clear-resets')->everyFifteenMinutes()->onOneServer();

// SaaS mode: billing and usage (central). Trials, failed payments, grace periods → suspended.
if (Tenancy::enabled()) {
    Schedule::command('saas:check-subscriptions')->hourly()->onOneServer();
    Schedule::command('saas:collect-usage')->dailyAt('04:30')->onOneServer();
}

// Horizon metrics graphs.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->when(fn () => config('queue.default') === 'redis');
