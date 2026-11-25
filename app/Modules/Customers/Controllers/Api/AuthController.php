<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Resources\CustomerResource;
use App\Modules\Customers\Support\CustomerAccounts;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Customer sign up / sign in for your mobile app.
 *
 *   POST   /api/v1/shop/auth/register         → {token, customer}
 *   POST   /api/v1/shop/auth/token            → {token, customer}   (code = 2FA code when needed)
 *   POST   /api/v1/shop/auth/forgot-password  → emails a reset link
 *   POST   /api/v1/shop/auth/reset-password   → sets a new password with the emailed token
 *   DELETE /api/v1/shop/auth/token            → sign out this device
 */
#[Group('Shop · Sign in', 'Customer accounts for your mobile app. Staff use /api/v1/auth/token instead.', weight: 10)]
class AuthController extends Controller
{
    /**
     * Create an account.
     *
     * Returns a token straight away. When `customer.needs_verification` is true, ask the customer
     * to click the link in the confirmation email (resend with POST /shop/email/verification-notification).
     *
     * @unauthenticated
     */
    public function register(Request $request): JsonResponse
    {
        abort_unless(CustomerAccounts::registrationOpen(), 403, 'Sign up is closed. Please contact us.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'marketing_opt_in' => ['boolean'],
            /** Shown to the customer under "Signed-in apps", e.g. "Priya's iPhone". */
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $customer = CustomerAccounts::register($data);
        event(new Login('customer-api', $customer, false));

        return $this->tokenResponse($customer, $customer->createToken($data['device_name'], ['*'], CustomerAccounts::tokenExpiry()));
    }

    /**
     * Sign in and get a token.
     *
     * If the customer turned on two-factor login, the first call fails with a `code` error and
     * `two_factor: true`: show a code field, then call again with `code` (6 digits, or a recovery code).
     *
     * @unauthenticated
     */
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:30'],
        ]);

        $customer = Customer::query()->where('email', Str::lower($data['email']))->first();

        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            event(new Failed('customer-api', $customer, ['email' => $data['email']]));

            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        if (! $customer->isActive()) {
            throw ValidationException::withMessages(['email' => __('Your account has been blocked. Please contact us.')]);
        }

        if ($customer->two_factor_confirmed_at && ! CustomerAccounts::validTwoFactorCode($customer, $data['code'] ?? null)) {
            if (filled($data['code'] ?? null)) {
                event(new Failed('customer-api', $customer, ['email' => $data['email']]));
            }

            return response()->json([
                'message' => filled($data['code'] ?? null) ? 'That code is not valid.' : 'Enter the code from your authenticator app.',
                'two_factor' => true,
                'errors' => ['code' => [filled($data['code'] ?? null) ? 'That code is not valid.' : 'Enter the code from your authenticator app.']],
            ], 422);
        }

        event(new Login('customer-api', $customer, false));

        return $this->tokenResponse($customer, $customer->createToken($data['device_name'], ['*'], CustomerAccounts::tokenExpiry()));
    }

    /**
     * Email a password reset link.
     *
     * Always answers the same way, so nobody can find out which emails have accounts.
     * The link opens the website's reset page.
     *
     * @unauthenticated
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker('customers')->sendResetLink(['email' => Str::lower((string) $request->input('email'))]);

        return response()->json(['message' => 'If that email has an account, a reset link is on its way.']);
    }

    /**
     * Set a new password with the token from the reset email.
     *
     * Use this if your app opens the reset link itself (deep link) instead of the website.
     * Signs the customer out of the app on every device.
     *
     * @unauthenticated
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('customers')->reset(
            ['email' => Str::lower((string) $request->input('email'))] + $request->only('password', 'password_confirmation', 'token'),
            function (Customer $customer, string $password) {
                $customer->forceFill(['password' => $password, 'remember_token' => Str::random(60)]);
                $customer->email_verified_at ??= now();
                $customer->save();
                $customer->tokens()->delete();
                event(new PasswordReset($customer));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Password changed. Sign in with the new password.']);
    }

    /**
     * Sign out (revoke the token used for this request).
     */
    public function revoke(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $customer->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function tokenResponse(Customer $customer, NewAccessToken $token): JsonResponse
    {
        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'customer' => new CustomerResource($customer->load('defaultAddress')),
        ], 201);
    }
}
