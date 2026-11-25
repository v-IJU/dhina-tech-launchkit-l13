<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users flagged with "force password change" can only use their profile
 * page until they set a new password.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->force_password_reset && ! $request->routeIs('admin.profile.*', 'logout', 'user-password.update', 'password.confirm*')) {
            return redirect()->route('admin.profile.edit', ['tab' => 'security'])
                ->with('warning', __('Please choose a new password before continuing.'));
        }

        return $next($request);
    }
}
