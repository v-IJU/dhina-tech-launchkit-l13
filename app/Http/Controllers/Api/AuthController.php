<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Token sign-in for mobile apps and other frontends.
 *
 *   POST   /api/v1/auth/token   {email, password, device_name, code?}  → {token, user}
 *   GET    /api/v1/me                                               → current user + permissions
 *   DELETE /api/v1/auth/token                                       → revoke the token used
 *
 * Send the token as:  Authorization: Bearer <token>
 */
class AuthController extends Controller
{
    /**
     * Sign in and get a token.
     *
     * Send `code` (6 digits from the authenticator app, or a recovery code) when the account uses two-factor login.
     * The token never expires unless Settings > Security sets a lifetime.
     *
     * @unauthenticated
     */
    public function token(Request $request): JsonResponse
    {
        abort_unless(setting('api_enabled', true), 503, 'The API is turned off.');

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::query()->where('email', Str::lower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            event(new Failed('sanctum', $user, ['email' => $data['email']]));

            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['email' => "This account is {$user->status}."]);
        }

        // Accounts with two-factor authentication must also send the current 6-digit code.
        if ($user->two_factor_confirmed_at) {
            if (! $this->validTwoFactorCode($user, (string) ($data['code'] ?? ''))) {
                event(new Failed('sanctum', $user, ['email' => $data['email']]));

                throw ValidationException::withMessages(['code' => 'Enter the 6-digit code from your authenticator app (or a recovery code).']);
            }
        } elseif ($user->require_two_factor || (setting('enforce_2fa') && $user->hasAnyRole([config('starter.super_admin_role'), 'admin']))) {
            throw ValidationException::withMessages(['email' => 'Set up two-factor authentication in the admin panel before using the API.']);
        }

        event(new Login('sanctum', $user, false)); // sign-in history, last login, new-device email

        $token = $user->createToken($data['device_name'], ['*'], $this->expiry());

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * Current user, roles and permissions.
     *
     * Use `permissions` to show or hide features in the app (`["*"]` = super admin, everything allowed).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    /**
     * Sign out (revoke the token used for this request).
     */
    public function revoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->isSuperAdmin() ? ['*'] : $user->getAllPermissions()->pluck('name')->values(),
            'two_factor' => (bool) $user->two_factor_confirmed_at,
        ];
    }

    private function validTwoFactorCode(User $user, string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        if (preg_match('/^\d{6}$/', $code)) {
            return app(TwoFactorAuthenticationProvider::class)->verify(decrypt($user->two_factor_secret), $code);
        }

        // Recovery code: valid once.
        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];
        if (in_array($code, $codes, true)) {
            $user->replaceRecoveryCode($code);

            return true;
        }

        return false;
    }

    private function expiry(): ?\DateTimeInterface
    {
        $days = (int) setting('api_token_days', 0);

        return $days > 0 ? now()->addDays($days) : null;
    }
}
