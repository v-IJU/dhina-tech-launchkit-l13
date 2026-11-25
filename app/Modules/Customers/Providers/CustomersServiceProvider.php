<?php

declare(strict_types=1);

namespace App\Modules\Customers\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Loaded automatically (app/Modules/*\/Providers/*ServiceProvider.php).
 */
class CustomersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Website sign-in form: Settings > Security > "Sign-in attempts per minute", per email + IP.
        RateLimiter::for('customer-login', fn (Request $request) => Limit::perMinute((int) setting('login_attempts', 5))
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Two-factor code page and "forgot password" emails.
        RateLimiter::for('customer-2fa', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('customer.login.id', $request->ip())));
        RateLimiter::for('customer-mail', fn (Request $request) => Limit::perMinute(3)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
