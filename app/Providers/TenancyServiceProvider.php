<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Tenancy\TenantAwareDatabaseSessionHandler;
use App\Http\Middleware\IdentifyTenant;
use App\Modules\Saas\Jobs\CreateTenantOwner;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;

/**
 * SaaS mode wiring (stancl/tenancy). Harmless in single-business mode: nothing here runs
 * unless a tenant is created or tenancy is initialised (IdentifyTenant does that per request).
 *
 * New tenant (signup or "Onboard tenant"):
 *   create database → run migrations → seed roles/permissions/menus/settings → create the owner account
 * Runs right away (not queued) so the tenant can sign in straight after signing up.
 */
class TenancyServiceProvider extends ServiceProvider
{
    /**
     * @return array<class-string, array<int, mixed>>
     */
    public function events(): array
    {
        return [
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    Jobs\SeedDatabase::class,
                    CreateTenantOwner::class,
                ])->send(fn (Events\TenantCreated $event) => $event->tenant)->shouldBeQueued(false),
            ],
            Events\TenantDeleted::class => [
                JobPipeline::make([Jobs\DeleteDatabase::class])
                    ->send(fn (Events\TenantDeleted $event) => $event->tenant)->shouldBeQueued(false),
            ],
            Events\TenancyInitialized::class => [Listeners\BootstrapTenancy::class],
            Events\TenancyEnded::class => [Listeners\RevertToCentralContext::class],
        ];
    }

    public function boot(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener instanceof JobPipeline ? $listener->toListener() : $listener);
            }
        }

        // Database sessions follow the tenant's database (see the handler for why).
        $this->app->make('session')->extend('database', fn ($app) => new TenantAwareDatabaseSessionHandler(
            $app['db']->connection($app['config']['session.connection']),
            (string) $app['config']['session.table'],
            (int) $app['config']['session.lifetime'],
            $app,
        ));

        // Identify the tenant before sessions, auth and everything else.
        $this->app->make(Kernel::class)->prependToMiddlewarePriority(IdentifyTenant::class);
    }
}
