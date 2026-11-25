<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users must set up two-factor login before using the panel when:
 *  - their account has "Require two-factor login" ticked (Users > Edit), or
 *  - Settings > Security > "Require 2FA for admin roles" is on and they are super-admin/admin.
 * Until then every admin page redirects to Profile > Security.
 */
class EnforceTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $required = $user && (
            $user->require_two_factor                                   // Users > Edit > "Require two-factor login"
            || (setting('enforce_2fa') && $user->hasAnyRole([config('starter.super_admin_role'), 'admin'])) // Settings > Security
        );

        if (
            $required
            && ! $user->two_factor_confirmed_at
            && ! $request->session()->has('impersonator_id')
            && ! $request->routeIs('admin.profile.*', 'logout', 'two-factor.*', 'password.confirm*', 'user-password.update')
        ) {
            return redirect()->route('admin.profile.edit', ['tab' => 'security'])
                ->with('warning', __('Your account requires two-factor login. Please set it up to continue.'));
        }

        return $next($request);
    }
}
