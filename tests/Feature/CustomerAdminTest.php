<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LoginEvent;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Notifications\CustomerResetPassword;
use App\Modules\Customers\Notifications\CustomerVerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Admin panel: Customers.
 */
class CustomerAdminTest extends TestCase
{
    public function test_list_filters_export_and_permissions(): void
    {
        Customer::factory()->create(['name' => 'Anita Raj', 'email' => 'anita@example.com']);
        Customer::factory()->blocked()->create(['name' => 'Blocked Bob']);
        Customer::factory()->unverified()->create(['name' => 'New Nila']);

        $this->actingAs($this->superAdmin(), 'web');
        $this->get($this->admin('customers'))->assertOk()->assertSee('Anita Raj')->assertSee('Blocked Bob');
        $this->get($this->admin('customers?status=blocked'))->assertSee('Blocked Bob')->assertDontSee('Anita Raj');
        $this->get($this->admin('customers?verified=no'))->assertSee('New Nila')->assertDontSee('Anita Raj');
        $this->get($this->admin('customers?q=anita'))->assertSee('Anita Raj')->assertDontSee('Blocked Bob');

        $csv = $this->get($this->admin('customers?export=csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('anita@example.com', $csv);

        // Editors have no customer permissions by default.
        $this->actingAs($this->userWithRole('editor'), 'web');
        $this->get($this->admin('customers'))->assertForbidden();
    }

    public function test_add_customer_sends_set_password_email_and_shows_profile(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin(), 'web');

        $this->get(route('admin.customers.create'))->assertOk();
        $this->post(route('admin.customers.store'), [
            'name' => 'Meera Das', 'email' => 'Meera@Example.com', 'phone' => '9811111111', 'status' => 'active',
            'email_verified' => 1, 'send_invite' => 1, 'notes' => 'VIP',
        ])->assertRedirect();

        $customer = Customer::query()->where('email', 'meera@example.com')->firstOrFail();
        $this->assertNotNull($customer->email_verified_at);
        Notification::assertSentTo($customer, CustomerResetPassword::class, fn (CustomerResetPassword $n) => $n->newAccount);

        $customer->addresses()->create(['label' => 'Home', 'name' => 'Meera', 'line1' => '7 Beach Rd', 'city' => 'Kochi', 'country' => 'India']);
        $this->get(route('admin.customers.show', $customer))->assertOk()
            ->assertSee('Meera Das')->assertSee('7 Beach Rd')->assertSee('VIP')->assertSee('Send password reset link');

        $this->put(route('admin.customers.update', $customer), ['name' => 'Meera D', 'email' => 'meera@example.com', 'status' => 'active', 'email_verified' => 1])
            ->assertRedirect(route('admin.customers.show', $customer));
        $this->assertSame('Meera D', $customer->fresh()->name);
    }

    public function test_account_actions(): void
    {
        Notification::fake();
        $customer = Customer::factory()->unverified()->create();
        $customer->createToken('Phone');
        $customer->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($this->superAdmin(), 'web');

        $this->post(route('admin.customers.resend-verification', $customer))->assertRedirect();
        Notification::assertSentTo($customer, CustomerVerifyEmail::class);
        $this->post(route('admin.customers.verify-email', $customer))->assertRedirect();
        $this->assertNotNull($customer->fresh()->email_verified_at);

        $this->post(route('admin.customers.password-reset', $customer))->assertSessionHas('success');
        Notification::assertSentTo($customer, CustomerResetPassword::class);

        $this->delete(route('admin.customers.two-factor.reset', $customer))->assertRedirect();
        $this->assertNull($customer->fresh()->two_factor_confirmed_at);

        $this->post(route('admin.customers.block', $customer))->assertRedirect();
        $this->assertSame('blocked', $customer->fresh()->status);
        $this->assertSame(0, $customer->tokens()->count());
        $this->post(route('admin.customers.block', $customer));
        $this->assertSame('active', $customer->fresh()->status);
    }

    public function test_bulk_block_delete_and_trash(): void
    {
        [$a, $b] = Customer::factory()->count(2)->create();
        $this->actingAs($this->superAdmin(), 'web');

        $this->post(route('admin.customers.bulk'), ['action' => 'block', 'ids' => [$a->id, $b->id]])->assertRedirect();
        $this->assertSame(2, Customer::query()->where('status', 'blocked')->count());

        $this->post(route('admin.customers.bulk'), ['action' => 'delete', 'ids' => [$a->id]])->assertRedirect();
        $this->assertSoftDeleted($a);
        $this->get($this->admin('customers?trashed=1'))->assertSee($a->name);
        $this->patch(route('admin.customers.restore', $a))->assertRedirect();
        $this->assertNotSoftDeleted($a);
    }

    public function test_sign_in_as_customer_keeps_admin_session(): void
    {
        $customer = Customer::factory()->create();
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'web');

        $this->post(route('admin.customers.impersonate', $customer))->assertRedirect(route('customer.account'));
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertDatabaseMissing('login_events', ['customer_id' => $customer->id, 'event' => 'login']);
        $this->get(route('customer.account'))->assertOk()->assertSee('Back to admin panel');

        $this->post(route('customer.impersonate.stop'))->assertRedirect(route('admin.customers.show', $customer));
        $this->assertGuest('customer');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_addresses_are_scoped_to_the_customer(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $theirs = $other->addresses()->create(['name' => 'X', 'line1' => '1 Road', 'city' => 'Pune', 'country' => 'India']);
        $this->actingAs($this->superAdmin(), 'web');

        $this->post(route('admin.customers.addresses.store', $customer), ['name' => 'C', 'line1' => '2 Road', 'city' => 'Goa', 'country' => 'India'])
            ->assertRedirect(route('admin.customers.show', $customer));
        $this->assertTrue($customer->addresses()->first()->is_default);

        $this->get(route('admin.customers.addresses.edit', [$customer, $theirs]))->assertNotFound();
    }

    public function test_import_and_sign_in_history(): void
    {
        Notification::fake();
        $existing = Customer::factory()->create(['email' => 'old@example.com', 'name' => 'Old Name']);
        $this->actingAs($this->superAdmin(), 'web');

        $csv = "Name,Email,Phone,Status\nNew Name,old@example.com,,active\nFresh Face,fresh@example.com,9000000001,active\n";
        $this->post(route('admin.customers.import.store'), ['file' => UploadedFile::fake()->createWithContent('c.csv', $csv), 'dry_run' => 0])
            ->assertOk()->assertSee('1 created, 1 updated', false);
        $this->assertSame('New Name', $existing->fresh()->name);
        Notification::assertSentTo(Customer::query()->where('email', 'fresh@example.com')->firstOrFail(), CustomerResetPassword::class);

        // Customer sign-ins have their own history page and stay out of the staff one.
        LoginEvent::query()->create(['customer_id' => $existing->id, 'guard' => 'customer', 'event' => 'login', 'email' => 'old@example.com', 'ip_address' => '10.0.0.9']);
        $this->get(route('admin.customers.logins'))->assertOk()->assertSee('10.0.0.9');
        $this->get(route('admin.users.logins'))->assertOk()->assertDontSee('10.0.0.9');
    }
}
