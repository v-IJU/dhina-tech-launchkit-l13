<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Middleware;

use App\Modules\Customers\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a customer out of the website as soon as staff block them.
 */
class EnsureCustomerIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if ($customer instanceof Customer && ! $customer->isActive()) {
            Auth::guard('customer')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('customer.login')->withErrors(['email' => __('Your account has been blocked. Please contact us.')]);
        }

        return $next($request);
    }
}
