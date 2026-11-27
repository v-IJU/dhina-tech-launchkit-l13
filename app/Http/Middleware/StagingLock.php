<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staging behind a password (browser login box), so the public, Google and bots never see it.
 * .env:  STAGING_BASIC_AUTH=client:Some-Shared-Password   (empty = off; production: leave empty)
 *
 * Not locked: /up (load balancer health check) and payment webhooks (the gateways can't log in).
 * The REST API (/api) has its own token login and is not affected.
 */
class StagingLock
{
    public function handle(Request $request, Closure $next): Response
    {
        $credentials = (string) config('starter.staging_basic_auth');
        if ($credentials === '' || ! str_contains($credentials, ':') || $request->is('up', 'billing/webhooks/*')) {
            return $next($request);
        }

        [$user, $password] = explode(':', $credentials, 2);
        if (hash_equals($user, (string) $request->getUser()) && hash_equals($password, (string) $request->getPassword())) {
            return $next($request);
        }

        return response('This staging site is private.', 401, ['WWW-Authenticate' => 'Basic realm="Staging", charset="UTF-8"']);
    }
}
