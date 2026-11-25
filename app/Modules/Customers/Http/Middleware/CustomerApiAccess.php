<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Middleware;

use App\Modules\Customers\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after auth:sanctum on every /api/v1/shop route (group "api.customer"):
 *  - the API can be switched off in Settings > Security
 *  - only customer tokens are accepted (staff tokens are for /api/v1)
 *  - blocked customers can't use their tokens
 */
class CustomerApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('api_enabled', true)) {
            return response()->json(['message' => 'The API is turned off.'], 503);
        }

        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return response()->json(['message' => 'This token cannot use the shop API.'], 403);
        }

        if (! $customer->isActive()) {
            return response()->json(['message' => 'This account has been blocked.'], 403);
        }

        return $next($request);
    }
}
