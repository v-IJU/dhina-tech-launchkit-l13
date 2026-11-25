<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Modules\ModuleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Added to every module's routes by ModuleServiceProvider ("module:blog").
 * 404 when the module can't be used here: switched off, central-only, tenant-only,
 * or not included in the tenant's plan (SaaS mode).
 */
class EnsureModuleAvailable
{
    public function __construct(private readonly ModuleManager $modules) {}

    public function handle(Request $request, Closure $next, string $slug): Response
    {
        abort_unless($this->modules->isEnabled($slug), 404);

        return $next($request);
    }
}
