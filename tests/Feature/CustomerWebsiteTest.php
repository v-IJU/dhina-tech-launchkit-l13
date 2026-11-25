<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Settings\SettingsRepository;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Notifications\CustomerResetPassword;
use App\Modules\Customers\Notifications\CustomerVerifyEmail;
use App\Modules\Customers\Notifications\CustomerWelcome;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Customer accounts on the public website: /account/...
 */
class CustomerWebsiteTest extends TestCase
{
    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create($attributes + ['email' => 'priya@example.com']);
    }

    public function test_sign_up_confirm_email_and_open_my_account(): void
    {
        Notification::fake();

        $this->get(route('customer.register'))->assertOk()->assertSee('Create your account');
        $this->post(route('customer.register.store'), [
            'name' => 'Priya Nair', 'email' => 'Priya@Example.com', 'phone' => '9876543210',
            'password' => 'Secret#123', 'password_confirmation' => 'Secret#123', 'terms' => '1',
        ])->assertRedirect(route('customer.account'));

        $customer = Customer::query()->where('email', 'priya@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($customer, 'customer');
        Notification::assertSentTo($customer, CustomerWelcome::class);

        // Email not confirmed yet: only the "check your inbox" page.
        $this->get(route('customer.account'))->assertRedirect(route('customer.verification.notice'));
        $this->get(route('customer.verification.notice'))->assertOk()->assertSee('priya@example.com');

        $url = null;
        Notification::assertSentTo($customer, CustomerVerifyEmail::class, function (CustomerVerifyEmail $n) use ($customer, &$url) {
            $url = $n->toMail($customer)->actionUrl;

            return true;
        });
        $this->get($url)->assertRedirect(route('customer.account'));
        $this->assertTrue($customer->fresh()->hasVerifiedEmail());

        $this->actingAs($customer->fresh(), 'customer'); // tests keep the signed-in model in memory between requests
        $this->get(route('customer.account'))->assertOk()->assertSee('Priya Nair')->assertSee('My account');
        $this->get('/')->assertSee(route('customer.account'));
    }

    public function test_sign_up_rejects_bots_and_needs_terms(): void
    {
        $data = ['name' => 'Bot', 'email' => 'bot@example.com', 'password' => 'Secret#123', 'password_confirmation' => 'Secret#123'];

        $this->post(route('customer.register.store'), $data)->assertSessionHasErrors('terms');
        $this->post(route('customer.register.store'), $data + ['terms' => 1, 'website' => 'spam.example'])->assertSessionHasErrors('website');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_sign_in_failures_blocked_accounts_and_history(): void
    {
        $customer = $this->customer();

        $this->post(route('customer.login.store'), ['email' => 'priya@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest('customer');
        $this->assertDatabaseHas('login_events', ['customer_id' => $customer->id, 'event' => 'failed', 'guard' => 'customer']);

        $this->post(route('customer.login.store'), ['email' => 'PRIYA@example.com', 'password' => 'Secret#123'])->assertRedirect(route('customer.account'));
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertDatabaseHas('login_events', ['customer_id' => $customer->id, 'event' => 'login', 'guard' => 'customer', 'user_id' => null]);
        $this->assertNotNull($customer->fresh()->last_login_at);

        // Staff block them: next page view signs them out.
        $customer->update(['status' => 'blocked']);
        $this->actingAs($customer->fresh(), 'customer');
        $this->get(route('customer.account'))->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
        $this->post(route('customer.login.store'), ['email' => 'priya@example.com', 'password' => 'Secret#123'])->assertSessionHasErrors('email');
    }

    public function test_customers_and_staff_are_separate(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        // A customer session is not an admin session.
        $this->get($this->admin())->assertRedirect(route('login'));

        // Signing out of the website keeps a staff session in the same browser.
        $staff = $this->superAdmin();
        $this->actingAs($staff, 'web');
        $this->post(route('customer.logout'))->assertRedirect('/');
        $this->assertGuest('customer');
        $this->assertAuthenticatedAs($staff, 'web');

        // Signed-out visitors are sent to the customer sign-in page, not the admin one.
        $this->get(route('customer.addresses.index'))->assertRedirect(route('customer.login'));
    }

    public function test_two_factor_setup_and_sign_in_with_code_or_recovery_code(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $this->post(route('customer.two-factor.manage', 'enable'), ['password' => 'wrong'])->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->post(route('customer.two-factor.manage', 'enable'), ['password' => 'Secret#123'])->assertRedirect();
        $this->get(route('customer.security'))->assertOk()->assertSee('<svg', false);

        $otp = fn () => (new Google2FA)->getCurrentOtp(decrypt($customer->fresh()->two_factor_secret));
        $this->post(route('customer.two-factor.manage', 'confirm'), ['code' => '000000'])->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');
        $this->post(route('customer.two-factor.manage', 'confirm'), ['code' => $otp()])->assertRedirect();
        $this->assertNotNull($customer->fresh()->two_factor_confirmed_at);
        $this->get(route('customer.security'))->assertSee('Save these recovery codes');

        $this->post(route('customer.logout'));
        Cache::flush();

        $this->post(route('customer.login.store'), ['email' => 'priya@example.com', 'password' => 'Secret#123'])->assertRedirect(route('customer.two-factor'));
        $this->assertGuest('customer');
        $this->post(route('customer.two-factor.store'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->post(route('customer.two-factor.store'), ['code' => $otp()])->assertRedirect(route('customer.account'));
        $this->assertAuthenticatedAs($customer, 'customer');

        // A recovery code works once.
        $this->post(route('customer.logout'));
        $recovery = $customer->fresh()->recoveryCodes()[0];
        $this->post(route('customer.login.store'), ['email' => 'priya@example.com', 'password' => 'Secret#123']);
        $this->post(route('customer.two-factor.store'), ['code' => $recovery])->assertRedirect(route('customer.account'));
        $this->assertNotContains($recovery, $customer->fresh()->recoveryCodes());

        $this->post(route('customer.two-factor.manage', 'disable'), ['password' => 'Secret#123'])->assertRedirect();
        $this->assertNull($customer->fresh()->two_factor_secret);
    }

    public function test_forgot_and_reset_password(): void
    {
        Notification::fake();
        $customer = $this->customer();

        $this->post(route('customer.password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('status');
        $this->post(route('customer.password.email'), ['email' => 'priya@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($customer, CustomerResetPassword::class, function (CustomerResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get(route('customer.password.reset', ['token' => $token, 'email' => 'priya@example.com']))->assertOk();
        $this->post(route('customer.password.update'), [
            'token' => $token, 'email' => 'priya@example.com', 'password' => 'NewSecret#456', 'password_confirmation' => 'NewSecret#456',
        ])->assertRedirect(route('customer.login'));

        $this->post(route('customer.login.store'), ['email' => 'priya@example.com', 'password' => 'NewSecret#456'])->assertRedirect(route('customer.account'));
    }

    public function test_addresses_and_default_address(): void
    {
        $customer = $this->customer();
        $other = Customer::factory()->create();
        $theirs = $other->addresses()->create(['name' => 'Other', 'line1' => '1 Road', 'city' => 'Kochi', 'country' => 'India']);
        $this->actingAs($customer, 'customer');

        $address = ['label' => 'Home', 'name' => 'Priya', 'line1' => '12 MG Road', 'city' => 'Chennai', 'postal_code' => '600001', 'country' => 'India'];
        $this->post(route('customer.addresses.store'), $address)->assertRedirect(route('customer.addresses.index'));
        $this->post(route('customer.addresses.store'), ['label' => 'Office'] + $address)->assertRedirect();

        [$home, $office] = $customer->addresses()->get()->sortBy('id')->values()->all();
        $this->assertTrue($home->is_default);
        $this->assertFalse($office->is_default);

        $this->post(route('customer.addresses.default', $office))->assertRedirect();
        $this->assertTrue($office->fresh()->is_default);
        $this->assertFalse($home->fresh()->is_default);

        $this->get(route('customer.addresses.index'))->assertOk()->assertSee('12 MG Road');
        $this->get(route('customer.addresses.edit', $theirs))->assertNotFound();
        $this->delete(route('customer.addresses.destroy', $theirs))->assertNotFound();

        $this->delete(route('customer.addresses.destroy', $office))->assertRedirect();
        $this->assertTrue($home->fresh()->is_default); // default moves to the next one
    }

    public function test_profile_password_and_delete_account(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $this->put(route('customer.profile.update'), ['name' => 'Priya N', 'email' => 'priya@example.com', 'phone' => '900'])->assertRedirect();
        $this->assertSame('Priya N', $customer->fresh()->name);

        // A new email needs the current password, then must be confirmed again.
        $this->put(route('customer.profile.update'), ['name' => 'Priya N', 'email' => 'new@example.com'])->assertSessionHasErrorsIn('profile', 'current_password');
        Notification::fake();
        $this->put(route('customer.profile.update'), ['name' => 'Priya N', 'email' => 'new@example.com', 'current_password' => 'Secret#123'])
            ->assertRedirect(route('customer.verification.notice'));
        $this->assertNull($customer->fresh()->email_verified_at);
        Notification::assertSentTo($customer, CustomerVerifyEmail::class);
        $customer = $customer->fresh();
        $customer->markEmailAsVerified();
        $this->actingAs($customer, 'customer');

        $this->put(route('customer.password.change'), ['current_password' => 'Secret#123', 'password' => 'Other#4567', 'password_confirmation' => 'Other#4567'])->assertRedirect();
        $this->delete(route('customer.destroy'), ['password' => 'wrong'])->assertSessionHasErrorsIn('deleteAccount', 'password');
        $this->delete(route('customer.destroy'), ['password' => 'Other#4567'])->assertRedirect('/');
        $this->assertSoftDeleted($customer);
        $this->assertGuest('customer');
    }

    public function test_settings_switch_sign_up_and_accounts_off(): void
    {
        app(SettingsRepository::class)->set(['customer_registration' => false]);
        $this->get(route('customer.register'))->assertNotFound();
        $this->get(route('customer.login'))->assertOk()->assertDontSee('Create an account');

        app(SettingsRepository::class)->set(['customer_accounts' => false]);
        $this->get(route('customer.login'))->assertNotFound();
        $this->get('/')->assertDontSee(route('customer.login'));
    }
}
