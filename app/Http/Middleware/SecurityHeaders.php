<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers to every web response. The Content-Security-Policy
 * uses a per-request nonce; use nonce="{{ Vite::cspNonce() }}" on any inline
 * <script> you add.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // microphone=(self): Team chat voice notes, only our own pages may ask (the browser still asks the user).
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(), payment=()');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // /docs/api (API documentation) loads its viewer from a CDN, and the log viewer (/admin/logs) uses an
        // inline script, so those two get no CSP. Both are for signed-in staff only.
        $exempt = $request->is('docs/*') || $request->is(trim((string) config('log-viewer.route_path'), '/').'*');
        if (config('starter.csp.enabled') && ! $response->headers->has('Content-Security-Policy') && ! $exempt) {
            $response->headers->set('Content-Security-Policy', $this->policy());
        }

        return $response;
    }

    private function policy(): string
    {
        $nonce = Vite::cspNonce();
        $dev = app()->isLocal() ? ' http://localhost:5173 http://127.0.0.1:5173' : '';
        $devWs = app()->isLocal() ? ' ws://localhost:5173 ws://127.0.0.1:5173' : '';

        $reverb = '';
        if (config('broadcasting.default') === 'reverb') {
            $host = config('starter.reverb_public.host');
            $port = config('starter.reverb_public.port');
            $scheme = config('starter.reverb_public.scheme') === 'https' ? 'wss' : 'ws';
            $reverb = " {$scheme}://{$host}".($port ? ":{$port}" : '');
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'".$dev,
            "style-src 'self' 'unsafe-inline'".$dev,
            "img-src 'self' ".implode(' ', config('starter.csp.img_src')),
            "font-src 'self' data:".$dev,
            "media-src 'self' blob:",
            "connect-src 'self'".$reverb.$dev.$devWs,
            'frame-src '.implode(' ', config('starter.csp.frame_src')),
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
