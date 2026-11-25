<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Tenancy\Tenancy;
use App\Modules\Saas\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * SaaS mode: decide from the host name whether this request is for the central app or a tenant.
 *
 *   yourapp.com            (CENTRAL_DOMAIN)  → central: pricing, signup, tenants console
 *   acme.yourapp.com       (subdomain)       → tenant "acme": its database, cache, files
 *   admin.acmecorp.com     (custom domain)   → the same tenant, if added under its domains
 *
 * Runs first in the "web" and "api" groups, so sessions, settings and sign-in all use the
 * tenant's database. Single-business mode: does nothing.
 *
 * A suspended tenant (unpaid, cancelled) only gets the sign-in and Billing pages.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Tenancy::enabled()) {
            return $next($request);
        }

        $host = strtolower($request->getHost());

        if (Tenancy::isCentralHost($host)) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            return $next($request);
        }

        $central = Tenancy::centralDomain();
        $key = Str::endsWith($host, '.'.$central) ? Str::beforeLast($host, '.'.$central) : $host;

        try {
            /** @var Tenant $tenant */
            $tenant = app(DomainTenantResolver::class)->resolve($key);
        } catch (\Throwable) {
            return response()->view('errors.tenant-not-found', ['host' => $host, 'central' => $central], 404);
        }

        // Always start from a fresh tenant (plan or status may have changed; Octane / tests reuse the app).
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        tenancy()->initialize($tenant);

        if ($tenant->isBlocked() && ! $this->allowedWhileBlocked($request)) {
            // Admin panel: straight to Billing (sign-in first if needed), so the owner can pay.
            $admin = trim((string) config('starter.admin_path'), '/');
            if (! $request->expectsJson() && $request->isMethod('GET') && $request->is($admin, $admin.'/*')) {
                return redirect('/'.$admin.'/billing');
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'This account is suspended. The account owner can renew it under Billing.'], 402)
                : response()->view('errors.tenant-suspended', ['tenant' => $tenant], 402);
        }

        return $next($request);
    }

    /**
     * Owners must be able to sign in and pay.
     */
    private function allowedWhileBlocked(Request $request): bool
    {
        $admin = trim((string) config('starter.admin_path'), '/');

        return $request->is(
            $admin.'/login', $admin.'/logout', $admin.'/two-factor-challenge', $admin.'/forgot-password', $admin.'/reset-password*',
            $admin.'/billing', $admin.'/billing/*', $admin.'/user/confirm-password', 'impersonate/*', 'up',
        );
    }
}
