<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Middleware;

use App\Modules\Customers\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages / endpoints that need a confirmed email address
 * (only when Settings > Customers > "Customers must verify their email" is on).
 *
 *   Route::middleware('customer.verified')->group(...)
 */
class EnsureCustomerIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer') ?? $request->user();

        if ($customer instanceof Customer && $customer->needsVerification()) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Please confirm your email address first.',
                    'email_verified' => false,
                ], 403);
            }

            return redirect()->route('customer.verification.notice');
        }

        return $next($request);
    }
}
