<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerPasskey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Passkey sign-in (fingerprint, Face ID, Windows Hello, phone) for admin users AND customers,
 * on top of laravel/passkeys. One place that:
 *
 *   - picks the right tables: admin users → passkeys, customers → customer_passkeys
 *   - binds passkeys to the CURRENT host (SaaS: each tenant's own address; single: your domain)
 *   - keeps the WebAuthn challenge in the session between the two steps
 *
 * Flow for both: options() → browser asks for the fingerprint → verify()/store().
 */
class PasskeyService
{
    public const STAFF = 'staff';

    public const CUSTOMER = 'customer';

    /** Use the tables of this kind of account and this host's identity. */
    public function for(string $kind, Request $request): static
    {
        if ($kind === self::CUSTOMER) {
            Passkeys::useUserModel(Customer::class);
            Passkeys::usePasskeyModel(CustomerPasskey::class);
        } else {
            Passkeys::useUserModel(User::class);
            Passkeys::usePasskeyModel(Passkey::class);
        }

        // Passkeys belong to a domain. Use the address the person is on (tenant hosts in SaaS mode).
        config([
            'passkeys.relying_party_id' => $request->getHost(),
            'passkeys.allowed_origins' => [$request->getSchemeAndHttpHost()],
        ]);

        return $this;
    }

    /**
     * Step 1 of signing in: a challenge for any passkey saved on this device.
     *
     * @return array<string, mixed>
     */
    public function loginOptions(Request $request, string $kind): array
    {
        $options = app(GenerateVerificationOptions::class)();
        $request->session()->put($this->key($kind, 'verify'), WebAuthn::toJson($options));

        return WebAuthn::toBrowserArray($options);
    }

    /**
     * Step 2 of signing in: check the signed challenge. Returns the passkey; its ->user is who signs in.
     */
    public function verify(Request $request, string $kind): Passkey
    {
        $request->validate([
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ]);

        /** @var PublicKeyCredentialRequestOptions $options */
        $options = $this->pull($request, $kind, 'verify', PublicKeyCredentialRequestOptions::class);

        try {
            return app(VerifyPasskey::class)($this->credential($request), $options);
        } catch (InvalidPasskeyException $e) {
            throw ValidationException::withMessages(['credential' => $e->getMessage()]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['credential' => __('This passkey could not be checked. Please try again or sign in with your password.')]);
        }
    }

    /**
     * Step 1 of adding a passkey to a signed-in account.
     *
     * @return array<string, mixed>
     */
    public function registrationOptions(Request $request, string $kind, PasskeyUser $user): array
    {
        $options = app(GenerateRegistrationOptions::class)($user);
        $request->session()->put($this->key($kind, 'register'), WebAuthn::toJson($options));

        return WebAuthn::toBrowserArray($options);
    }

    /** Step 2 of adding a passkey: store the new credential under the given name. */
    public function store(Request $request, string $kind, PasskeyUser $user, string $name): Passkey
    {
        $request->validate([
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ]);

        /** @var PublicKeyCredentialCreationOptions $options */
        $options = $this->pull($request, $kind, 'register', PublicKeyCredentialCreationOptions::class);

        try {
            return app(StorePasskey::class)($user, $name, $this->credential($request), $options);
        } catch (InvalidPasskeyException $e) {
            throw ValidationException::withMessages(['credential' => $e->getMessage()]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['credential' => __('This passkey could not be saved. Please try again.')]);
        }
    }

    /** A sensible default name: "Chrome on Windows", "Safari on iPhone"… */
    public function defaultName(Request $request): string
    {
        $agent = new UserAgent((string) $request->userAgent());

        return trim($agent->browser().' on '.$agent->platform()) ?: 'Passkey '.Date::now()->format('d M Y');
    }

    private function credential(Request $request): PublicKeyCredential
    {
        try {
            return WebAuthn::fromJson((string) json_encode($request->input('credential')), PublicKeyCredential::class);
        } catch (Throwable) {
            throw ValidationException::withMessages(['credential' => __('Invalid passkey response.')]);
        }
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function pull(Request $request, string $kind, string $step, string $class): object
    {
        $json = $request->session()->pull($this->key($kind, $step));
        if (! is_string($json)) {
            throw ValidationException::withMessages(['credential' => __('The passkey request expired. Please try again.')]);
        }

        return WebAuthn::fromJson($json, $class);
    }

    private function key(string $kind, string $step): string
    {
        return "passkeys.{$kind}.{$step}";
    }
}
