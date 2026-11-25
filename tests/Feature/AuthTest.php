<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get($this->admin())->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Sign in to admin');
    }

    public function test_active_user_can_sign_in(): void
    {
        $user = $this->superAdmin();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Secret#123'])
            ->assertRedirect($this->admin());

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->superAdmin();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_or_suspended_users_cannot_sign_in(): void
    {
        foreach (['inactive', 'suspended'] as $status) {
            $user = $this->userWithRole('editor', ['status' => $status]);

            $this->post(route('login'), ['email' => $user->email, 'password' => 'Secret#123'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = $this->userWithRole('admin');
        $this->actingAs($user);
        $user->update(['status' => 'inactive']);

        $this->get($this->admin())->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_forced_password_change_redirects_to_profile(): void
    {
        $user = $this->userWithRole('admin', ['force_password_reset' => true]);

        $this->actingAs($user)->get($this->admin('users'))->assertRedirect(route('admin.profile.edit', ['tab' => 'security']));
        $this->actingAs($user)->get(route('admin.profile.edit', ['tab' => 'security']))->assertOk();
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get($this->admin('register'))->assertNotFound();
    }

    public function test_per_user_two_factor_requirement_redirects_to_setup(): void
    {
        $user = $this->userWithRole('editor', ['require_two_factor' => true]);

        $this->actingAs($user)->get($this->admin())->assertRedirect(route('admin.profile.edit', ['tab' => 'security']));
        $this->get(route('admin.profile.edit', ['tab' => 'security']))->assertOk();

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->get($this->admin())->assertOk();
    }

    public function test_two_factor_setup_and_sign_in_with_a_code(): void
    {
        $user = $this->userWithRole('editor');
        $this->actingAs($user);

        // One step: password on the same form, QR code straight after.
        $this->post(route('admin.profile.two-factor', 'enable'), ['password' => 'wrong'])->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->post(route('admin.profile.two-factor', 'enable'), ['password' => 'Secret#123'])->assertRedirect(route('admin.profile.edit', ['tab' => 'security']));
        $this->get(route('admin.profile.edit', ['tab' => 'security']))->assertSee('<svg', false);

        $otp = fn () => (new Google2FA)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
        $this->post(route('two-factor.confirm'), ['code' => $otp()])->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        // Next sign-in asks for the code after the password.
        auth()->logout();
        Cache::flush(); // a code can only be used once; this lets the test reuse the current one
        $this->post(route('login'), ['email' => $user->email, 'password' => 'Secret#123'])->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['code' => $otp()])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('admin.profile.two-factor', 'disable'), ['password' => 'Secret#123'])->assertRedirect();
        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
