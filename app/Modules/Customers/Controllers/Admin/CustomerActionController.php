<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

/**
 * Buttons on the customer profile page (admin panel):
 * block / unblock, send password reset, confirm or resend email, reset 2FA,
 * sign out of the app, and "Sign in as customer" (opens the website as them).
 */
class CustomerActionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.edit', except: ['impersonate']),
            new Middleware('permission:customers.impersonate', only: ['impersonate']),
        ];
    }

    public function toggleBlock(Customer $customer): RedirectResponse
    {
        $blocking = $customer->isActive();
        $customer->update(['status' => $blocking ? 'blocked' : 'active']);
        if ($blocking) {
            $customer->tokens()->delete();
        }

        return back()->with('success', $blocking
            ? "{$customer->name} is blocked: signed out everywhere and can't sign in."
            : "{$customer->name} can sign in again.");
    }

    public function sendPasswordReset(Customer $customer): RedirectResponse
    {
        $status = Password::broker('customers')->sendResetLink(['email' => $customer->email]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', "Password reset link sent to {$customer->email}.")
            : back()->with('error', __($status));
    }

    public function verifyEmail(Customer $customer): RedirectResponse
    {
        $customer->markEmailAsVerified();

        return back()->with('success', 'Email address marked as confirmed.');
    }

    public function resendVerification(Customer $customer): RedirectResponse
    {
        $customer->sendEmailVerificationNotification();

        return back()->with('success', "Confirmation email sent to {$customer->email}.");
    }

    /**
     * For a customer who lost their phone and their recovery codes.
     */
    public function resetTwoFactor(Customer $customer): RedirectResponse
    {
        app(DisableTwoFactorAuthentication::class)($customer);
        activity('customers')->performedOn($customer)->event('2fa-reset')->log('Two-factor login reset by staff');

        return back()->with('success', "Two-factor login turned off for {$customer->name}. They can set it up again from My account.");
    }

    public function revokeApps(Customer $customer): RedirectResponse
    {
        $count = $customer->tokens()->delete();

        return back()->with('success', "Signed out of {$count} app session(s).");
    }

    public function revokeApp(Customer $customer, int $token): RedirectResponse
    {
        $customer->tokens()->whereKey($token)->delete();

        return back()->with('success', 'App session signed out.');
    }

    /**
     * Opens the website signed in as this customer (to see what they see).
     * Uses the separate "customer" guard, so your admin session stays signed in.
     * Not recorded in their sign-in history; the amber bar on the website ends it.
     */
    public function impersonate(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->isActive(), 422, 'Blocked customers cannot be signed in as.');

        $request->session()->put('customer_impersonated_by', $request->user()->id);
        Auth::guard('customer')->login($customer);
        activity('customers')->performedOn($customer)->causedBy($request->user())->event('impersonate')->log('Signed in as customer');

        return redirect()->route('customer.account');
    }
}
