<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeUser;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Priya', 'last_name' => 'Nair', 'email' => 'priya@example.com', 'username' => 'priya.nair',
            'password' => 'Strong#Pass1', 'password_confirmation' => 'Strong#Pass1',
            'role' => 'editor', 'status' => 'active', 'email_verified' => '1', 'send_welcome' => '1',
            'require_two_factor' => '0', 'force_password_reset' => '0', 'country' => 'India',
        ];
    }

    public function test_admin_creates_a_user_with_role_and_welcome_email(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.users.store'), $this->payload())->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'priya@example.com')->firstOrFail();
        $this->assertSame('Priya Nair', $user->name);
        $this->assertTrue($user->hasRole('editor'));
        $this->assertNotNull($user->email_verified_at);
        Notification::assertSentTo($user, WelcomeUser::class);
    }

    public function test_validation_errors_are_shown_per_field(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.users.store'), $this->payload(['email' => 'not-an-email', 'password' => 'weak', 'password_confirmation' => 'weak', 'username' => 'Bad Name!']))
            ->assertSessionHasErrors(['email', 'password', 'username']);
    }

    public function test_admin_cannot_grant_super_admin_role(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $this->post(route('admin.users.store'), $this->payload(['role' => 'super-admin']))->assertSessionHasErrors('role');
    }

    public function test_update_keeps_password_when_left_empty(): void
    {
        $this->actingAs($this->superAdmin());
        $user = $this->userWithRole('editor');
        $hash = $user->password;

        $this->put(route('admin.users.update', $user), $this->payload(['email' => $user->email, 'username' => $user->username, 'password' => '', 'password_confirmation' => '', 'role' => 'admin', 'first_name' => 'Ravi']))
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame($hash, $user->password);
        $this->assertSame('Ravi Nair', $user->name);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_bulk_deactivate_and_delete(): void
    {
        $me = $this->superAdmin();
        $a = $this->userWithRole('editor');
        $b = $this->userWithRole('editor');
        $this->actingAs($me);

        $this->post(route('admin.users.bulk'), ['action' => 'deactivate', 'ids' => [$a->id, $b->id, $me->id]])->assertRedirect();
        $this->assertSame('inactive', $a->fresh()->status);
        $this->assertSame('active', $me->fresh()->status, 'cannot change own account in bulk');

        $this->post(route('admin.users.bulk'), ['action' => 'delete', 'ids' => [$a->id]])->assertRedirect();
        $this->assertSoftDeleted($a);
    }

    public function test_cannot_delete_own_account(): void
    {
        $me = $this->superAdmin();
        $this->actingAs($me)->delete(route('admin.users.destroy', $me))->assertStatus(422);
    }

    public function test_export_csv(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->get(route('admin.users.index', ['export' => 'csv']))->assertOk();
        $this->assertStringContainsString('Email', $response->baseResponse->getFile()->getContent());
    }

    public function test_impersonate_and_return(): void
    {
        $me = $this->superAdmin();
        $editor = $this->userWithRole('editor');

        $this->actingAs($me)->post(route('admin.users.impersonate', $editor))->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($editor);

        $this->post(route('admin.users.impersonate.stop'))->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($me);
    }
}
