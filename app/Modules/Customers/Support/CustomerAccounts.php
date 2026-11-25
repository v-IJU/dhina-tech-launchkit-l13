<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Notifications\CustomerWelcome;
use App\Notifications\AdminAlert;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Customer account logic shared by the website, the shop API and the admin panel,
 * so sign-up and two-factor rules are the same everywhere.
 */
class CustomerAccounts
{
    /**
     * Create an account from the sign-up form or POST /api/v1/shop/auth/register.
     *
     * @param  array{name: string, email: string, password: string, phone?: string|null, marketing_opt_in?: bool}  $data
     */
    public static function register(array $data): Customer
    {
        $customer = Customer::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
            'locale' => app()->getLocale(),
            'status' => 'active',
        ]);

        if (setting('customer_verify_email', true)) {
            $customer->sendEmailVerificationNotification();
        } else {
            $customer->markEmailAsVerified();
        }

        if (setting('customer_welcome_email', true)) {
            $customer->notify(new CustomerWelcome);
        }

        if (setting('customer_notify_admins', false)) {
            AdminAlert::toAdmins("New customer {$customer->name} signed up", route('admin.customers.show', $customer), 'customers.view', 'bi-person-plus');
        }

        return $customer;
    }

    public static function registrationOpen(): bool
    {
        return (bool) setting('customer_registration', true);
    }

    public static function twoFactorAllowed(): bool
    {
        return (bool) setting('customer_2fa', true);
    }

    /**
     * A 6-digit authenticator code, or one of the recovery codes (each works once).
     */
    public static function validTwoFactorCode(Customer $customer, ?string $code): bool
    {
        $code = trim((string) $code);
        if ($code === '' || ! $customer->two_factor_secret) {
            return false;
        }

        if (preg_match('/^\d{6}$/', $code)) {
            return app(TwoFactorAuthenticationProvider::class)->verify(decrypt($customer->two_factor_secret), $code);
        }

        if (in_array($code, $customer->recoveryCodes(), true)) {
            $customer->replaceRecoveryCode($code);

            return true;
        }

        return false;
    }

    /**
     * Expiry for app tokens (Settings > Customers > "App sign-in lasts"). Null = never.
     */
    public static function tokenExpiry(): ?\DateTimeInterface
    {
        $days = (int) setting('customer_token_days', 365);

        return $days > 0 ? now()->addDays($days) : null;
    }
}
