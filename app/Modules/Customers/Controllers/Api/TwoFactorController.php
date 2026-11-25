<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Support\CustomerAccounts;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * Two-factor login set up from inside the app (same as My account > Security on the website).
 *
 *   1. POST   /shop/two-factor          {password}  → secret, otpauth_url, qr_svg (not active yet)
 *   2. POST   /shop/two-factor/confirm  {code}      → active + recovery_codes (show once)
 *   3. POST   /shop/two-factor/recovery-codes {password} → new recovery codes
 *      DELETE /shop/two-factor          {password}  → off
 */
#[Group('Shop · Two-factor login', weight: 12)]
class TwoFactorController extends Controller
{
    /**
     * Start setup.
     *
     * Show `qr_svg` (or open `otpauth_url` in an authenticator app on the same phone),
     * then send the 6-digit code to /shop/two-factor/confirm.
     */
    public function enable(Request $request): JsonResponse
    {
        abort_unless(CustomerAccounts::twoFactorAllowed(), 403, 'Two-factor login is not available.');
        $customer = $this->checkPassword($request);

        app(EnableTwoFactorAuthentication::class)($customer, true);
        $customer->refresh();

        return response()->json([
            'secret' => decrypt((string) $customer->two_factor_secret),
            'otpauth_url' => $customer->twoFactorQrCodeUrl(),
            'qr_svg' => $customer->twoFactorQrCodeSvg(),
        ]);
    }

    /**
     * Finish setup with a code from the app.
     *
     * Returns `recovery_codes`: tell the customer to save them; they are not shown again unless regenerated.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $customer = $this->customer($request);

        try {
            app(ConfirmTwoFactorAuthentication::class)($customer, (string) $request->input('code'));
        } catch (ValidationException) {
            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        return response()->json(['message' => 'Two-factor login is on.', 'recovery_codes' => $customer->recoveryCodes()]);
    }

    /**
     * New recovery codes (the old ones stop working).
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $customer = $this->checkPassword($request);
        abort_unless($customer->two_factor_confirmed_at !== null, 422, 'Two-factor login is not on.');

        app(GenerateNewRecoveryCodes::class)($customer);

        return response()->json(['recovery_codes' => $customer->refresh()->recoveryCodes()]);
    }

    /**
     * Turn off two-factor login.
     */
    public function disable(Request $request): JsonResponse
    {
        app(DisableTwoFactorAuthentication::class)($this->checkPassword($request));

        return response()->json(['message' => 'Two-factor login is off.']);
    }

    private function checkPassword(Request $request): Customer
    {
        $request->validate(['password' => ['required', 'string']]);
        $customer = $this->customer($request);

        if (! Hash::check((string) $request->input('password'), $customer->password)) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }

        return $customer;
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user();
    }
}
