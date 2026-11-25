<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Settings\SettingsRepository;
use App\Models\LoginEvent;
use App\Notifications\AdminAlert;
use App\Notifications\NewDeviceSignIn;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    private const CHROME_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private function signIn(string $email, string $password, string $agent): void
    {
        $this->withHeader('User-Agent', $agent)->post(route('login'), ['email' => $email, 'password' => $password]);
        auth()->logout();
        $this->flushSession();
    }

    public function test_sign_ins_and_failures_are_recorded(): void
    {
        $user = $this->userWithRole('editor');

        $this->signIn($user->email, 'Secret#123', self::CHROME_WIN);
        $this->signIn($user->email, 'wrong-password', self::CHROME_WIN);
        $this->signIn('nobody@example.com', 'whatever', self::CHROME_WIN);

        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'login', 'browser' => 'Chrome', 'platform' => 'Windows']);
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'failed']);
        $this->assertDatabaseHas('login_events', ['user_id' => null, 'email' => 'nobody@example.com', 'event' => 'failed']);
    }

    public function test_new_device_sends_an_email_but_first_sign_in_does_not(): void
    {
        Notification::fake();
        $user = $this->userWithRole('editor');

        $this->signIn($user->email, 'Secret#123', self::CHROME_WIN);   // first ever: no email
        $this->signIn($user->email, 'Secret#123', self::CHROME_WIN);   // same device: no email
        Notification::assertNothingSentTo($user);

        $this->signIn($user->email, 'Secret#123', self::SAFARI_IPHONE); // new device
        Notification::assertSentTo($user, NewDeviceSignIn::class);
        $this->assertTrue(LoginEvent::query()->latest('id')->where('event', 'login')->first()->is_new_device);
    }

    public function test_new_device_email_can_be_turned_off(): void
    {
        Notification::fake();
        app(SettingsRepository::class)->set(['notify_new_device' => false]);
        $user = $this->userWithRole('editor');

        $this->signIn($user->email, 'Secret#123', self::CHROME_WIN);
        $this->signIn($user->email, 'Secret#123', self::SAFARI_IPHONE);

        Notification::assertNotSentTo($user, NewDeviceSignIn::class);
    }

    public function test_lockout_alerts_admins(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $user = $this->userWithRole('editor');

        foreach (range(1, 5) as $i) {
            $this->withHeader('User-Agent', self::CHROME_WIN)->post(route('login'), ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->assertDatabaseHas('login_events', ['email' => $user->email, 'event' => 'lockout']);
        Notification::assertSentTo($admin, AdminAlert::class);
    }

    public function test_history_page_and_profile(): void
    {
        $user = $this->userWithRole('editor');
        $this->signIn($user->email, 'Secret#123', self::CHROME_WIN);

        $this->actingAs($this->superAdmin());
        $this->get(route('admin.users.logins', ['event' => 'login']))->assertOk()->assertSee($user->email)->assertSee('Chrome on Windows');
        $this->get(route('admin.users.logins', ['event' => 'failed']))->assertOk()->assertDontSee($user->email);

        $this->actingAs($user);
        $this->get(route('admin.profile.edit', ['tab' => 'security']))->assertOk()->assertSee('Recent sign-ins')->assertSee('Chrome on Windows');
    }

    public function test_editor_cannot_see_history_page(): void
    {
        $this->actingAs($this->userWithRole('editor'));
        $this->get(route('admin.users.logins'))->assertForbidden();
    }
}
