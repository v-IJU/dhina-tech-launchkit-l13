<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Support\CustomerAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * /account/security — two-factor login, recent sign-ins, signed-in apps.
 *
 * Two-factor setup in 3 steps (same as staff):
 *   1. enable  (password)  → secret + QR code are created, not active yet
 *   2. confirm (6-digit code from the app) → active, recovery codes shown once
 *   3. disable / recovery-codes (password) at any time
 */
class SecurityController extends Controller
{
    public function show(Request $request): View
    {
        $customer = $this->customer($request);

        return view('customers::web.account.security', [
            'customer' => $customer,
            'logins' => $customer->loginEvents()->limit(10)->get(),
            'apps' => $customer->tokens()->latest()->get(),
            'passkeys' => $customer->passkeys()->latest()->get(),
        ]);
    }

    public function twoFactor(Request $request, string $action): RedirectResponse
    {
        $customer = $this->customer($request);
        abort_unless(CustomerAccounts::twoFactorAllowed() || $action === 'disable', 403);

        if ($action === 'confirm') {
            $request->validateWithBag('twoFactor', ['code' => ['required', 'string']]);
            app(ConfirmTwoFactorAuthentication::class)($customer, (string) $request->input('code'));

            return back()->with('two_factor', 'confirmed');
        }

        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password:customer']]);

        match ($action) {
            'enable' => app(EnableTwoFactorAuthentication::class)($customer, true),
            'disable' => app(DisableTwoFactorAuthentication::class)($customer),
            default => app(GenerateNewRecoveryCodes::class)($customer),
        };

        $message = match ($action) {
            'enable' => __('Scan the QR code with your authenticator app, then enter the 6-digit code.'),
            'disable' => __('Two-factor login is turned off.'),
            default => __('New recovery codes created. The old ones no longer work.'),
        };

        return back()->with('success', $message)->with('two_factor', $action);
    }

    /**
     * Sign out of the mobile app on every device (deletes every app token).
     */
    public function signOutApps(Request $request): RedirectResponse
    {
        $request->validateWithBag('apps', ['password' => ['required', 'current_password:customer']]);
        $this->customer($request)->tokens()->delete();

        return back()->with('success', __('You have been signed out of the app on every device.'));
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('customer');
    }
}
