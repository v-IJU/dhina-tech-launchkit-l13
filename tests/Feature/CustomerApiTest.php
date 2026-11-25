<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Settings\SettingsRepository;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Notifications\CustomerResetPassword;
use App\Modules\Customers\Notifications\CustomerVerifyEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Customer (shop) API for mobile apps: /api/v1/shop/...
 */
class CustomerApiTest extends TestCase
{
    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create($attributes + ['email' => 'ravi@example.com']);
    }

    private function tokenFor(Customer $customer): string
    {
        return $customer->createToken('Test phone')->plainTextToken;
    }

    public function test_register_get_profile_and_sign_out(): void
    {
        Notification::fake();

        $register = $this->postJson('/api/v1/shop/auth/register', [
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'password' => 'Secret#123',
            'password_confirmation' => 'Secret#123', 'device_name' => 'Ravi iPhone',
        ])->assertCreated()
            ->assertJsonPath('customer.email', 'ravi@example.com')
            ->assertJsonPath('customer.email_verified', false)
            ->assertJsonPath('customer.needs_verification', true);

        $customer = Customer::query()->where('email', 'ravi@example.com')->firstOrFail();
        Notification::assertSentTo($customer, CustomerVerifyEmail::class);
        $this->assertDatabaseHas('login_events', ['customer_id' => $customer->id, 'event' => 'login', 'guard' => 'customer']);

        $token = $register->json('token');
        $this->withToken($token)->getJson('/api/v1/shop/me')->assertOk()->assertJsonPath('data.name', 'Ravi Kumar');
        $this->withToken($token)->putJson('/api/v1/shop/me', ['phone' => '9000000000', 'marketing_opt_in' => true])
            ->assertOk()->assertJsonPath('data.phone', '9000000000')->assertJsonPath('data.marketing_opt_in', true);

        $this->withToken($token)->deleteJson('/api/v1/shop/auth/token')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_token_sign_in_with_and_without_two_factor(): void
    {
        $customer = $this->customer();

        $this->postJson('/api/v1/shop/auth/token', ['email' => 'ravi@example.com', 'password' => 'wrong', 'device_name' => 'Phone'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/shop/auth/token', ['email' => 'RAVI@example.com', 'password' => 'Secret#123', 'device_name' => 'Phone'])
            ->assertCreated()->assertJsonStructure(['token', 'token_type', 'expires_at', 'customer' => ['id', 'email']]);

        // Turn on 2FA (same as the website): first call says "two_factor: true", second call sends the code.
        $token = $this->tokenFor($customer);
        $this->withToken($token)->postJson('/api/v1/shop/two-factor', ['password' => 'wrong'])->assertJsonValidationErrors('password');
        $setup = $this->withToken($token)->postJson('/api/v1/shop/two-factor', ['password' => 'Secret#123'])
            ->assertOk()->assertJsonStructure(['secret', 'otpauth_url', 'qr_svg'])->json();
        $otp = fn () => (new Google2FA)->getCurrentOtp($setup['secret']);

        $this->withToken($token)->postJson('/api/v1/shop/two-factor/confirm', ['code' => '000000'])->assertJsonValidationErrors('code');
        $codes = $this->withToken($token)->postJson('/api/v1/shop/two-factor/confirm', ['code' => $otp()])->assertOk()->json('recovery_codes');
        $this->assertCount(8, $codes);
        Cache::flush();

        $this->postJson('/api/v1/shop/auth/token', ['email' => 'ravi@example.com', 'password' => 'Secret#123', 'device_name' => 'Phone'])
            ->assertUnprocessable()->assertJsonPath('two_factor', true);
        $this->postJson('/api/v1/shop/auth/token', ['email' => 'ravi@example.com', 'password' => 'Secret#123', 'device_name' => 'Phone', 'code' => $otp()])
            ->assertCreated();
        $this->postJson('/api/v1/shop/auth/token', ['email' => 'ravi@example.com', 'password' => 'Secret#123', 'device_name' => 'Phone', 'code' => $codes[0]])
            ->assertCreated();

        $this->withToken($token)->deleteJson('/api/v1/shop/two-factor', ['password' => 'Secret#123'])->assertOk();
        $this->assertNull($customer->fresh()->two_factor_secret);
    }

    public function test_staff_and_customer_tokens_cannot_be_swapped(): void
    {
        $staffToken = $this->superAdmin()->createToken('Staff app')->plainTextToken;
        $customerToken = $this->tokenFor($this->customer());

        $this->withToken($staffToken)->getJson('/api/v1/shop/me')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($customerToken)->getJson('/api/v1/me')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($customerToken)->getJson('/api/v1/faqs')->assertForbidden();
    }

    public function test_blocked_customers_and_unconfirmed_email(): void
    {
        $customer = $this->customer(['email_verified_at' => null]);
        $token = $this->tokenFor($customer);

        // Profile works, addresses need a confirmed email.
        $this->withToken($token)->getJson('/api/v1/shop/me')->assertOk()->assertJsonPath('data.needs_verification', true);
        $this->withToken($token)->getJson('/api/v1/shop/addresses')->assertForbidden()->assertJsonPath('email_verified', false);

        Notification::fake();
        $this->withToken($token)->postJson('/api/v1/shop/email/verification-notification')->assertOk();
        Notification::assertSentTo($customer, CustomerVerifyEmail::class);

        $customer->update(['status' => 'blocked']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/shop/me')->assertForbidden();
        $this->postJson('/api/v1/shop/auth/token', ['email' => 'ravi@example.com', 'password' => 'Secret#123', 'device_name' => 'Phone'])
            ->assertJsonValidationErrors('email');
    }

    public function test_addresses_crud_and_isolation(): void
    {
        $customer = $this->customer();
        $other = Customer::factory()->create();
        $theirs = $other->addresses()->create(['name' => 'X', 'line1' => '1 Road', 'city' => 'Kochi', 'country' => 'India']);
        $token = $this->tokenFor($customer);

        $address = ['label' => 'Home', 'name' => 'Ravi', 'line1' => '5 Anna Salai', 'city' => 'Chennai', 'postal_code' => '600002', 'country' => 'India'];
        $first = $this->withToken($token)->postJson('/api/v1/shop/addresses', $address)->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');
        $second = $this->withToken($token)->postJson('/api/v1/shop/addresses', ['label' => 'Office'] + $address)->assertCreated()->assertJsonPath('data.is_default', false)->json('data.id');

        $this->withToken($token)->postJson("/api/v1/shop/addresses/{$second}/default")->assertOk()->assertJsonPath('data.is_default', true);
        $this->withToken($token)->getJson('/api/v1/shop/addresses')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $second);
        $this->withToken($token)->putJson("/api/v1/shop/addresses/{$first}", ['city' => 'Madurai'])->assertOk()->assertJsonPath('data.city', 'Madurai');
        $this->withToken($token)->getJson('/api/v1/shop/me')->assertJsonPath('data.default_address.id', $second);

        $this->withToken($token)->getJson("/api/v1/shop/addresses/{$theirs->id}")->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/shop/addresses/{$theirs->id}")->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/shop/addresses/{$first}")->assertOk();
    }

    public function test_forgot_reset_password_and_delete_account(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $oldToken = $this->tokenFor($customer);

        $this->postJson('/api/v1/shop/auth/forgot-password', ['email' => 'ravi@example.com'])->assertOk();
        $this->postJson('/api/v1/shop/auth/forgot-password', ['email' => 'unknown@example.com'])->assertOk();

        $resetToken = null;
        Notification::assertSentTo($customer, CustomerResetPassword::class, function (CustomerResetPassword $n) use (&$resetToken) {
            $resetToken = $n->token;

            return true;
        });

        $this->postJson('/api/v1/shop/auth/reset-password', ['token' => 'bad', 'email' => 'ravi@example.com', 'password' => 'New#Secret9', 'password_confirmation' => 'New#Secret9'])
            ->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/shop/auth/reset-password', ['token' => $resetToken, 'email' => 'ravi@example.com', 'password' => 'New#Secret9', 'password_confirmation' => 'New#Secret9'])
            ->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $customer->id, 'name' => 'Test phone']);
        unset($oldToken);

        $token = $this->postJson('/api/v1/shop/auth/token', ['email' => 'ravi@example.com', 'password' => 'New#Secret9', 'device_name' => 'Phone'])->assertCreated()->json('token');
        $this->withToken($token)->deleteJson('/api/v1/shop/me', ['password' => 'wrong'])->assertJsonValidationErrors('password');
        $this->withToken($token)->deleteJson('/api/v1/shop/me', ['password' => 'New#Secret9'])->assertOk();
        $this->assertSoftDeleted($customer);
    }

    public function test_settings_close_sign_up_and_turn_accounts_off(): void
    {
        app(SettingsRepository::class)->set(['customer_registration' => false]);
        $this->postJson('/api/v1/shop/auth/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'Secret#123', 'password_confirmation' => 'Secret#123', 'device_name' => 'P'])
            ->assertForbidden();

        app(SettingsRepository::class)->set(['customer_accounts' => false]);
        $this->postJson('/api/v1/shop/auth/token', ['email' => 'a@example.com', 'password' => 'x', 'device_name' => 'P'])->assertStatus(503);
    }

    public function test_shop_endpoints_are_in_the_api_docs(): void
    {
        $this->actingAs($this->superAdmin(), 'web');
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $this->assertArrayHasKey('/shop/auth/token', $spec['paths']);
        $this->assertArrayHasKey('/shop/addresses', $spec['paths']);
        $this->assertContains('Shop · Sign in', collect($spec['tags'] ?? [])->pluck('name')->all());
    }
}
