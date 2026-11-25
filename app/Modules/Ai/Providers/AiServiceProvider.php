<?php

declare(strict_types=1);

namespace App\Modules\Ai\Providers;

use App\Modules\Ai\Mcp\SiteServer;
use App\Modules\Ai\Middleware\McpAccess;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

/**
 * MCP endpoint: POST /mcp  (Streamable HTTP, Bearer = an API token from My profile).
 *
 * "api" group first: in SaaS mode it identifies the tenant from the host name, so
 * https://acme.yourapp.com/mcp works on acme's database only.
 */
class AiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['api', 'auth:sanctum', 'module:ai', McpAccess::class, 'throttle:api'])
            ->group(fn () => Mcp::web('mcp', SiteServer::class)->name('mcp.site'));
    }
}
