<?php

namespace Tests\Feature;

use App\Core\Security\PasskeyService;
use App\Core\Settings\SettingsRepository;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerPasskey;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;
use Tests\TestCase;

/**
 * Passkeys: the WebAuthn crypto itself is laravel/passkeys' job (and tested there). Here we test our
 * part: options bound to the current host, who may sign in, settings switches and passkey management.
 */
class PasskeyTest extends TestCase
{
    private function staffPasskey(User $user, string $name = 'My laptop'): Passkey
    {
        $passkey = new Passkey(['name' => $name, 'credential_id' => 'cred-'.$user->id.'-'.$name, 'credential' => ['x' => 1]]);
        $passkey->forceFill(['user_id' => $user->id])->save();

        return $passkey;
    }

    private function customerPasskey(Customer $customer): CustomerPasskey
    {
        $passkey = new CustomerPasskey(['name' => 'My phone', 'credential_id' => 'cust-'.$customer->id, 'credential' => ['x' => 1]]);
        $passkey->forceFill(['user_id' => $customer->id])->save();

        return $passkey;
    }

    /** Pretend the browser's fingerprint check passed and returned this passkey. */
    private function fakeVerified(Passkey $passkey): void
    {
        $this->partialMock(PasskeyService::class, function ($mock) use ($passkey) {
            $mock->shouldReceive('verify')->andReturnUsing(fn (Request $r, string $kind) => $passkey->fresh());
        });
    }

    public function test_login_options_are_bound_to_the_current_host(): void
    {
        $data = $this->getJson(route('passkey.login-options'))->assertOk()->json('options');

        $this->assertSame(request()->getHost(), $data['rpId']);
        $this->assertNotEmpty($data['challenge']);
        $this->assertNotNull(session('passkeys.staff.verify'));
    }

    public function test_admin_signs_in_with_a_passkey(): void
    {
        $user = $this->superAdmin();
        $this->fakeVerified($this->staffPasskey($user));

        $this->postJson(route('passkey.login'), ['credential' => ['id' => 'x']])
            ->assertOk()->assertJson(['redirect' => url((string) config('starter.admin_path'))]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_admin_cannot_sign_in_with_a_passkey(): void
    {
        $user = $this->userWithRole('editor', ['status' => 'suspended']);
        $this->fakeVerified($this->staffPasskey($user));

        $this->postJson(route('passkey.login'), ['credential' => ['id' => 'x']])->assertJsonValidationErrors('credential');
        $this->assertGuest();
    }

    public function test_passkey_sign_in_can_be_turned_off(): void
    {
        app(SettingsRepository::class)->set(['passkeys_enabled' => false]);

        $this->getJson(route('passkey.login-options'))->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee('Sign in with a passkey');
    }

    public function test_adding_a_passkey_needs_the_password(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->postJson(route('admin.profile.passkeys.options'), ['password' => 'wrong'])
            ->assertJsonValidationErrors('password');

        $options = $this->actingAs($user)->postJson(route('admin.profile.passkeys.options'), ['password' => 'Secret#123'])
            ->assertOk()->json('options');

        $this->assertSame($user->email, $options['user']['name']);
        $this->assertSame(request()->getHost(), $options['rp']['id']);
    }

    public function test_profile_lists_and_removes_own_passkeys_only(): void
    {
        $user = $this->superAdmin();
        $mine = $this->staffPasskey($user, 'Work laptop');
        $other = $this->staffPasskey($this->userWithRole('editor'), 'Not mine');

        $this->actingAs($user)->get(route('admin.profile.edit', ['tab' => 'security']))->assertOk()->assertSee('Work laptop')->assertDontSee('Not mine');

        $this->actingAs($user)->delete(route('admin.profile.passkeys.destroy', $other->id))->assertNotFound();
        $this->actingAs($user)->delete(route('admin.profile.passkeys.destroy', $mine->id))->assertRedirect();

        $this->assertModelMissing($mine);
        $this->assertModelExists($other);
    }

    public function test_a_passkey_satisfies_enforced_two_factor(): void
    {
        app(SettingsRepository::class)->set(['enforce_2fa' => true]);
        $user = $this->superAdmin();

        $this->actingAs($user)->get($this->admin())->assertRedirect();

        $this->staffPasskey($user);
        $this->actingAs($user->fresh())->get($this->admin())->assertOk();
    }

    public function test_customer_signs_in_with_a_passkey_and_staff_passkeys_stay_separate(): void
    {
        $customer = Customer::factory()->create();
        $this->fakeVerified($this->customerPasskey($customer));

        $this->postJson(route('customer.passkey.login'), ['credential' => ['id' => 'x']])
            ->assertOk()->assertJson(['redirect' => route('customer.account')]);

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
        $this->assertNotSame($customer->getPasskeyUserHandle(), User::query()->firstOrNew(['id' => $customer->id])->getPasskeyUserHandle());
    }

    public function test_blocked_customer_cannot_sign_in_with_a_passkey(): void
    {
        $customer = Customer::factory()->create(['status' => 'blocked']);
        $this->fakeVerified($this->customerPasskey($customer));

        $this->postJson(route('customer.passkey.login'), ['credential' => ['id' => 'x']])->assertJsonValidationErrors('credential');
        $this->assertGuest('customer');
    }

    public function test_customer_security_page_and_switch(): void
    {
        $this->getJson(route('customer.passkey.login-options'))->assertOk();
        app(SettingsRepository::class)->set(['customer_passkeys' => false]);
        $this->getJson(route('customer.passkey.login-options'))->assertNotFound();
        app(SettingsRepository::class)->set(['customer_passkeys' => true]);

        $customer = Customer::factory()->create();
        $passkey = $this->customerPasskey($customer);

        $this->actingAs($customer, 'customer')->get(route('customer.security'))->assertOk()->assertSee('My phone');
        $this->actingAs($customer, 'customer')->delete(route('customer.passkeys.destroy', $passkey->id))->assertRedirect(route('customer.security'));
        $this->assertModelMissing($passkey);
    }
}
