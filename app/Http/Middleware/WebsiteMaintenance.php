<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings > Maintenance mode: visitors see a "back soon" page while signed-in
 * staff keep using the site and admin panel normally.
 */
class WebsiteMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (setting('maintenance_mode') && ! $request->user()) {
            return response()->view('web.maintenance', [
                'message' => setting('maintenance_message', __('We are making some improvements. Back soon!')),
            ], 503);
        }

        return $next($request);
    }
}
