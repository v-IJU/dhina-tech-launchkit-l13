<?php

use App\Core\Monitoring\ErrorRecorder;
use App\Http\Middleware\ApiAccess;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureModuleAvailable;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetWebsiteLocale;
use App\Http\Middleware\StagingLock;
use App\Http\Middleware\WebsiteMaintenance;
use App\Modules\Customers\Http\Middleware\CustomerAccountsEnabled;
use App\Modules\Customers\Http\Middleware\CustomerApiAccess;
use App\Modules\Customers\Http\Middleware\EnsureCustomerIsActive;
use App\Modules\Customers\Http\Middleware\EnsureCustomerIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    // Artisan commands: core commands plus any app/Modules/*/Console folder.
    ->withCommands([
        __DIR__.'/../app/Core/Console',
        ...(glob(__DIR__.'/../app/Modules/*/Console', GLOB_ONLYDIR) ?: []),
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind nginx / a load balancer inside Docker or on AWS.
        $middleware->trustProxies(at: '*');

        // Laravel 13 request-forgery protection (on for every web route):
        //  - modern browsers: the Sec-Fetch-Site header must say the form/fetch came from this same site
        //  - older browsers / no header: falls back to the CSRF token as before
        // allowSameSite false = a sibling subdomain (another tenant, e.g. acme.example.com) can't post to
        // laravelnews.example.com. Payment webhooks are verified by signature instead, so they are excluded.
        $middleware->preventRequestForgery(except: ['billing/webhooks/*'], originOnly: false, allowSameSite: false);

        // SaaS mode: pick the tenant from the host before sessions start (no-op in single-business mode).
        $middleware->web(prepend: [StagingLock::class, IdentifyTenant::class], append: [SecurityHeaders::class]);
        $middleware->api(prepend: [IdentifyTenant::class, ForceJsonResponse::class]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'force.password' => ForcePasswordChange::class,
            'enforce.2fa' => EnforceTwoFactor::class,
            'api.access' => ApiAccess::class,
            'module' => EnsureModuleAvailable::class,
            'customer.enabled' => CustomerAccountsEnabled::class,
            'customer.active' => EnsureCustomerIsActive::class,
            'customer.verified' => EnsureCustomerIsVerified::class,
            'customer.api' => CustomerApiAccess::class,
        ]);

        // Every admin route (module Routes/admin.php files) runs through this group.
        $middleware->group('admin', ['web', 'auth:web', 'active', 'force.password', 'enforce.2fa']);

        // Every module API route file (/api/v1/..., /api/v2/...): token required, API switch, read-only tokens.
        $middleware->group('api.staff', ['api', 'auth:sanctum', 'api.access', 'throttle:api']);

        // Customer (shop) API, module Routes/shop.php and Routes/shop_guest.php files.
        $middleware->group('api.customer', ['api', 'customer.enabled', 'auth:sanctum', 'customer.api', 'throttle:api']);
        $middleware->group('api.customer.guest', ['api', 'customer.enabled', 'throttle:api-token']);

        // Public website routes (module Routes/web.php files).
        $middleware->group('website', [SetWebsiteLocale::class, WebsiteMaintenance::class]);

        // Signed-out visitors go to the right sign-in page: customers to /account/login, staff to the admin login.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('customer.*') ? route('customer.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('customer.*') ? route('customer.account') : route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Built-in error tracker: Admin > System > Error log, with bell/email alerts.
        $exceptions->report(function (Throwable $e) {
            ErrorRecorder::record($e);
        });

        // Optional Sentry: composer require sentry/sentry-laravel and set SENTRY_LARAVEL_DSN in .env.
        $sentry = 'Sentry\\Laravel\\Integration';
        if (class_exists($sentry)) {
            $sentry::handles($exceptions);
        }

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Spatie throws its own exception for missing permissions; show the normal 403 page.
        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You do not have permission to do this.'], 403);
            }

            return response()->view('errors.403', ['exception' => $e], 403);
        });
    })->create();
