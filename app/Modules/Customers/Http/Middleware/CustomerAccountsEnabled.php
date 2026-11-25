<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings > Customers > "Customer accounts" switch. When off, the website
 * account pages return 404 and the shop API returns 503.
 */
class CustomerAccountsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('customer_accounts', true)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Customer accounts are turned off.'], 503);
            }
            abort(404);
        }

        return $next($request);
    }
}
