<?php

declare(strict_types=1);

namespace App\Core\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires every enabled module into Laravel:
 *  - Routes/admin.php  -> /{admin_path}/..., route names "admin.*", admin middleware
 *  - Routes/web.php    -> public website routes
 *  - Routes/api.php    -> /api/v1/..., route names "api.*", Sanctum token required
 *    (more versions: config('starter.api_versions'), e.g. Routes/api_v2.php -> /api/v2/...)
 *  - Routes/shop.php       -> /api/v1/shop/..., names "api.shop.*", customer token required
 *  - Routes/shop_guest.php -> /api/v1/shop/..., public customer endpoints (register, sign in)
 *  Every group also gets "module:{slug}": 404 when the module can't be used in this
 *  context (switched off, or not in the tenant's plan in SaaS mode).
 *  - Views/            -> view namespace = module slug, e.g. view('blog::admin.index')
 *  - Database/Migrations, optional Providers/*ServiceProvider.php
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, fn () => new ModuleManager(config('starter.modules_path')));

        foreach ($this->manager()->enabled() as $module) {
            foreach (glob($module->file('Providers/*ServiceProvider.php')) ?: [] as $file) {
                $this->app->register($module->namespace().'\\Providers\\'.basename($file, '.php'));
            }
        }
    }

    public function boot(): void
    {
        $manager = $this->manager();

        // Migrations are loaded for every module (even disabled ones) so the
        // database schema never depends on which modules are switched on.
        foreach ($manager->all() as $module) {
            $this->loadMigrationsFrom($module->file('Database/Migrations'));
        }

        foreach ($manager->enabled() as $module) {
            if (is_dir($module->file('Views'))) {
                $this->loadViewsFrom($module->file('Views'), $module->slug);
            }
        }

        if (! $this->app->routesAreCached()) {
            $this->loadModuleRoutes($manager);
        }
    }

    private function loadModuleRoutes(ModuleManager $manager): void
    {
        $this->app->booted(function () use ($manager) {
            foreach ($manager->enabled() as $module) {
                if (is_file($file = $module->file('Routes/admin.php'))) {
                    Route::middleware(['admin', "module:{$module->slug}"])
                        ->prefix(config('starter.admin_path'))
                        ->name('admin.')
                        ->group($file);
                }
            }

            // API routes, one prefix per version in config('starter.api_versions'):
            //   v1 → Routes/api.php    → /api/v1/..., names "api.*"
            //   v2 → Routes/api_v2.php → /api/v2/..., names "api.v2.*"
            // Every version: Sanctum token + api.access + rate limit (middleware group "api.staff").
            foreach (config('starter.api_versions', ['v1' => 'Routes/api.php']) as $version => $routeFile) {
                foreach ($manager->enabled() as $module) {
                    if (is_file($file = $module->file($routeFile))) {
                        Route::middleware(['api.staff', "module:{$module->slug}"])
                            ->prefix("api/{$version}")
                            ->name($version === 'v1' ? 'api.' : "api.{$version}.")
                            ->group($file);
                    }
                }
            }

            // Customer (shop) API: same versions idea, separate token owners (Customer model).
            //   Routes/shop.php       → signed-in customers (middleware group "api.customer")
            //   Routes/shop_guest.php → public endpoints such as register and sign in
            foreach (config('starter.shop_api_versions', ['v1' => 'Routes/shop.php']) as $version => $routeFile) {
                $name = $version === 'v1' ? 'api.shop.' : "api.{$version}.shop.";
                foreach ($manager->enabled() as $module) {
                    if (is_file($file = $module->file(str_replace('.php', '_guest.php', $routeFile)))) {
                        Route::middleware(['api.customer.guest', "module:{$module->slug}"])->prefix("api/{$version}/shop")->name($name)->group($file);
                    }
                    if (is_file($file = $module->file($routeFile))) {
                        Route::middleware(['api.customer', "module:{$module->slug}"])->prefix("api/{$version}/shop")->name($name)->group($file);
                    }
                }
            }

            // Website routes are registered in module order; the Pages module
            // has the highest order so its catch-all "/{slug}" route comes last.
            foreach ($manager->enabled() as $module) {
                if (is_file($file = $module->file('Routes/web.php'))) {
                    Route::middleware(['web', 'website', "module:{$module->slug}"])->group($file);
                }
            }

            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        });
    }

    private function manager(): ModuleManager
    {
        return $this->app->make(ModuleManager::class);
    }
}
