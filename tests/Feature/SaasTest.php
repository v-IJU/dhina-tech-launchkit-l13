<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Settings\SettingsRepository;
use App\Models\User;
use App\Modules\Saas\Database\Seeders\PlanSeeder;
use App\Modules\Saas\Models\Invoice;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Support\PlanLimits;
use App\Modules\Saas\Support\TenantProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * SaaS mode (STARTER_TENANCY=true): central console, signup, tenant databases,
 * plan modules and limits, suspension, payments. Tenant databases are SQLite files
 * in database/ (tenant_<id>), removed after each test.
 */
class SaasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'starter.tenancy.enabled' => true,
            'tenancy.central_domains' => ['saas.test'],
            'app.url' => 'http://saas.test',
            'services.razorpay' => ['key' => 'rzp_test', 'secret' => 'sec', 'webhook_secret' => 'whsec_rzp'],
            'services.stripe' => ['secret' => 'sk_test', 'webhook_secret' => 'whsec_stripe'],
        ]);
        $this->seed(PlanSeeder::class);
        Notification::fake();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }
        foreach (glob(database_path('tenant_*')) ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function makeTenant(string $id = 'acme', string $plan = 'starter', array $extra = []): Tenant
    {
        return app(TenantProvisioner::class)->create($extra + [
            'name' => ucfirst($id).' Ltd',
            'subdomain' => $id,
            'owner_name' => 'Asha Kumar',
            'owner_email' => "owner@{$id}.com",
            'password' => 'Secret#123',
            'plan_id' => Plan::query()->where('slug', $plan)->value('id'),
        ]);
    }

    private function owner(Tenant $tenant): User
    {
        tenancy()->initialize($tenant);
        $user = User::query()->with('roles.permissions', 'permissions')->where('email', $tenant->email)->firstOrFail();
        tenancy()->end();

        return $user;
    }

    private function central(string $path = ''): string
    {
        return 'http://saas.test'.$this->admin($path);
    }

    private function host(string $id, string $path = ''): string
    {
        return "http://{$id}.saas.test/".ltrim($path, '/');
    }

    public function test_single_business_mode_has_no_saas_pages(): void
    {
        config(['starter.tenancy.enabled' => false]);

        $this->get('/pricing')->assertNotFound();
        $this->actingAs($this->superAdmin(), 'web')->get($this->central('saas'))->assertNotFound();
        $this->get($this->central('billing'))->assertNotFound();
        $this->get($this->central('blog/posts'))->assertOk();
    }

    public function test_signup_creates_tenant_database_and_owner(): void
    {
        $this->get('http://saas.test/pricing')->assertOk()->assertSee('Business');
        $this->get('http://saas.test/signup?plan=business')->assertOk();
        $this->getJson('http://saas.test/signup/check?subdomain=www')->assertJson(['available' => false]);
        $this->getJson('http://saas.test/signup/check?subdomain=acme')->assertJson(['available' => true]);

        $this->post('http://saas.test/signup', [
            'plan_id' => Plan::query()->where('slug', 'business')->value('id'),
            'name' => 'Acme Ltd', 'subdomain' => 'Acme', 'owner_name' => 'Asha Kumar', 'owner_email' => 'asha@acme.com',
            'password' => 'Secret#123', 'password_confirmation' => 'Secret#123', 'terms' => '1',
        ])->assertRedirect('http://acme.saas.test/admin/login?welcome=1');

        $tenant = Tenant::query()->findOrFail('acme');
        $this->assertSame('trial', $tenant->status);
        $this->assertTrue($tenant->trial_ends_at->isFuture());
        $this->assertNull($tenant->owner_password, 'password hash must not stay in the central DB');
        $this->assertFileExists(database_path('tenant_acme'));

        tenancy()->initialize($tenant);
        $this->assertTrue(User::query()->where('email', 'asha@acme.com')->firstOrFail()->hasRole('super-admin'));
        tenancy()->end();

        // The owner signs in on their own subdomain; the central admin of the SaaS does not exist there.
        $this->post($this->host('acme', 'admin/login'), ['email' => 'asha@acme.com', 'password' => 'Secret#123'])
            ->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_database_sessions_are_saved_in_the_tenant_database(): void
    {
        $tenant = $this->makeTenant('acme');
        config(['session.driver' => 'database']);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['auth']->forgetGuards();

        $this->post($this->host('acme', 'admin/login'), ['email' => 'owner@acme.com', 'password' => 'Secret#123'])->assertRedirect();
        tenancy()->end(); // the test app keeps the request's tenant

        $this->assertSame(0, DB::table('sessions')->whereNotNull('user_id')->count(), 'no tenant sign-in in the central sessions table');
        tenancy()->initialize($tenant);
        $this->assertSame(1, DB::table('sessions')->whereNotNull('user_id')->count());
        tenancy()->end();
    }

    public function test_tenant_data_is_isolated(): void
    {
        $acme = $this->makeTenant('acme');
        $beta = $this->makeTenant('beta');
        app(SettingsRepository::class)->set(['site_name' => 'Central Co']);

        // Read centrally first (as the app does while booting), then inside each tenant.
        $this->assertSame('Central Co', setting('site_name'));
        tenancy()->initialize($acme);
        $this->assertSame('Acme Ltd', setting('site_name'));
        $this->assertSame('Acme Ltd', config('app.name'), 'tenant settings are applied to config');
        tenancy()->end();
        $this->assertSame('Central Co', setting('site_name'));
        $this->assertSame('Central Co', config('app.name'));

        tenancy()->initialize($acme);
        app(SettingsRepository::class)->set(['site_name' => 'Only Acme']);
        tenancy()->end();

        tenancy()->initialize($beta);
        $this->assertSame('Beta Ltd', setting('site_name'));
        $this->assertSame(0, User::query()->where('email', 'owner@acme.com')->count());
        tenancy()->end();

        $this->assertSame(0, User::query()->where('email', 'owner@acme.com')->count(), 'central DB has no tenant users');

        // Saving settings through the tenant's panel changes only that tenant.
        $this->actingAs($this->owner($acme), 'web');
        $this->get($this->host('acme', 'admin/settings'))->assertOk()->assertSee('Only Acme')->assertDontSee('Central Co');
    }

    public function test_plan_decides_modules(): void
    {
        $tenant = $this->makeTenant('acme', 'starter');
        $owner = $this->owner($tenant);
        $this->actingAs($owner, 'web');

        $this->get($this->host('acme', 'admin'))->assertOk()
            ->assertSee('Starter')                                   // plan badge in the top bar
            ->assertSee(route('admin.billing.index'), false)         // Billing in the sidebar
            ->assertSee('Your plan')                                 // dashboard card instead of server status
            ->assertDontSee('System status')
            ->assertDontSee('admin/blog/posts');                     // no Blog link or "Write post" tile on Starter
        $this->get($this->host('acme', 'admin/blog/posts'))->assertNotFound();

        // Tenants assign permissions to roles, but can't invent new permission names.
        $this->get($this->host('acme', 'admin/permissions'))->assertOk()->assertDontSee('Create permission');
        $this->post($this->host('acme', 'admin/permissions'), ['name' => 'reports.view'])->assertForbidden();
        $this->get($this->host('acme', 'admin/billing'))->assertOk()->assertSee('Starter');
        $this->get($this->host('acme', 'admin/saas'))->assertNotFound();
        $this->get($this->host('acme', 'admin/modules'))->assertNotFound();

        $tenant->update(['plan_id' => Plan::query()->where('slug', 'business')->value('id')]);
        $this->get($this->host('acme', 'admin/blog/posts'))->assertOk();
        $this->get($this->host('acme', 'admin'))->assertSee('admin/blog/posts'); // sidebar follows the plan at once

        // An extra module for one tenant only.
        $tenant->update(['plan_id' => Plan::query()->where('slug', 'starter')->value('id'), 'extra_modules' => ['blog']]);
        $this->get($this->host('acme', 'admin/blog/posts'))->assertOk();
    }

    public function test_plan_limits_block_new_users(): void
    {
        $tenant = $this->makeTenant('acme', 'starter'); // 3 users
        tenancy()->initialize($tenant);

        $this->assertSame([1, 3], PlanLimits::usage()['users']);
        $this->userWithRole('editor');
        $this->userWithRole('editor');

        $this->expectException(ValidationException::class);
        $this->userWithRole('editor');
    }

    public function test_unknown_host_and_suspended_tenant(): void
    {
        $this->get($this->host('nobody'))->assertNotFound()->assertSee('doesn');

        $tenant = $this->makeTenant('acme');
        $tenant->update(['status' => 'suspended', 'suspended_at' => now()]);

        $this->get($this->host('acme'))->assertStatus(402);
        $this->get($this->host('acme', 'admin/login'))->assertOk();

        $this->actingAs($this->owner($tenant), 'web');
        $this->get($this->host('acme', 'admin'))->assertRedirect('/admin/billing');
        $this->get($this->host('acme', 'admin/users'))->assertRedirect('/admin/billing');
        $this->post($this->host('acme', 'admin/users'), [])->assertStatus(402);
        $this->get($this->host('acme', 'admin/billing'))->assertOk()->assertSee('suspended');
    }

    public function test_console_pages_and_onboarding(): void
    {
        $tenant = $this->makeTenant('acme');
        $this->actingAs($this->superAdmin(), 'web');

        foreach (['saas', 'saas/tenants', 'saas/tenants/create', 'saas/tenants/acme', 'saas/tenants/acme/edit', 'saas/subscriptions', 'saas/invoices', 'saas/plans', 'saas/plans/create'] as $page) {
            $this->assertSame(200, $this->get($this->central($page))->status(), $page);
        }
        // Website content modules also run your own SaaS website; Customers stays tenant-only.
        foreach (['blog/posts', 'blog/posts/create', 'faqs', 'services', 'sliders', 'testimonials'] as $page) {
            $this->assertSame(200, $this->get($this->central($page))->status(), $page);
        }
        $this->get($this->central('customers'))->assertNotFound();
        $this->get($this->central())->assertSee(route('admin.blog.posts.index'), false);

        $this->post($this->central('saas/tenants'), [
            'name' => 'Beta Traders', 'subdomain' => 'beta', 'owner_name' => 'Ravi', 'owner_email' => 'ravi@beta.com',
            'plan_id' => Plan::query()->where('slug', 'enterprise')->value('id'), 'trial_days' => 0,
        ])->assertRedirect($this->central('saas/tenants/beta'));

        $beta = Tenant::query()->findOrFail('beta');
        $this->assertSame('active', $beta->status);
        $this->assertNotNull($this->owner($beta));

        // Record a bank transfer → paid invoice with GST.
        $this->post($this->central('saas/tenants/acme/payments'), [
            'plan_id' => Plan::query()->where('slug', 'business')->value('id'), 'interval' => 'month', 'amount' => 2499, 'reference' => 'UTR123',
        ])->assertRedirect();
        $invoice = Invoice::query()->where('tenant_id', 'acme')->firstOrFail();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(249900, $invoice->amount);
        $this->assertSame(294882, $invoice->total);
        $this->assertSame('active', $tenant->fresh()->status);
        $this->get($this->central('saas/invoices/'.$invoice->id))->assertOk()->assertSee($invoice->number);

        // Plans: create with modules and limits; the pricing page shows public ones.
        $this->post($this->central('saas/plans'), [
            'name' => 'Agency', 'price_monthly' => 4999, 'price_yearly' => 49990, 'currency' => 'INR', 'trial_days' => 7,
            'modules' => ['blog', 'customers'], 'limits' => ['users' => 25, 'customers' => '', 'storage_mb' => 10240],
            'features' => "25 users\nBlog", 'is_active' => '1', 'is_public' => '1', 'sort_order' => 4,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $agency = Plan::query()->where('slug', 'agency')->firstOrFail();
        $this->assertSame(499900, $agency->price_monthly);
        $this->assertSame(25, $agency->limit('users'));
        $this->assertNull($agency->limit('customers'));
        $this->get('http://saas.test/pricing')->assertSee('Agency');

        // Edit: extra module + limit override for one tenant.
        $this->put($this->central('saas/tenants/acme'), [
            'name' => 'Acme Ltd', 'email' => 'owner@acme.com', 'plan_id' => $tenant->plan_id, 'status' => 'active',
            'extra_modules' => ['blog'], 'limit_overrides' => ['users' => 10],
        ])->assertRedirect();
        $this->assertSame(10, $tenant->fresh()->limit('users'));
        $this->assertContains('blog', $tenant->fresh()->moduleSlugs());

        // Open the tenant as its owner: one-time token on the tenant's own domain.
        $this->post($this->central('saas/tenants/acme/impersonate'))->assertRedirectContains('http://acme.saas.test/impersonate/');

        // Delete needs the id typed.
        $this->delete($this->central('saas/tenants/beta'), ['confirm' => 'nope'])->assertSessionHasErrors('confirm');
        $this->delete($this->central('saas/tenants/beta'), ['confirm' => 'beta'])->assertRedirect();
        $this->assertFileDoesNotExist(database_path('tenant_beta'));
    }

    public function test_razorpay_checkout_and_webhooks(): void
    {
        $plan = Plan::query()->where('slug', 'business')->firstOrFail();
        $plan->update(['razorpay_plan_monthly' => 'plan_RZP1']);
        Http::fake(['api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_RZP1', 'short_url' => 'https://rzp.io/i/abc'])]);

        $tenant = $this->makeTenant('acme');
        $this->actingAs($this->owner($tenant), 'web');
        $this->post($this->host('acme', 'admin/billing/checkout'), ['plan_id' => $plan->id, 'interval' => 'month', 'gateway' => 'razorpay'])
            ->assertRedirect('https://rzp.io/i/abc');

        $payload = json_encode([
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_RZP1', 'current_start' => now()->timestamp, 'current_end' => now()->addMonth()->timestamp]],
                'payment' => ['entity' => ['id' => 'pay_1', 'amount' => 294882, 'currency' => 'INR']],
            ],
        ]);

        $this->call('POST', 'http://saas.test/billing/webhooks/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'wrong'], $payload)->assertStatus(400);
        $this->call('POST', 'http://saas.test/billing/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $payload, 'whsec_rzp'),
        ], $payload)->assertOk();

        $this->assertSame('active', Subscription::query()->where('gateway_id', 'sub_RZP1')->value('status'));
        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertSame($plan->id, $tenant->fresh()->plan_id);
        $invoice = Invoice::query()->where('gateway_payment_id', 'pay_1')->firstOrFail();
        $this->assertSame(294882, $invoice->total);

        // The tenant sees its plan, invoice and plan picker.
        $this->get($this->host('acme', 'admin/billing'))->assertOk()->assertSee($invoice->number);
        $this->get($this->host('acme', 'admin/billing/plans'))->assertOk()->assertSee('Razorpay');
        $this->get($this->host('acme', 'admin/billing/invoices/'.$invoice->id))->assertOk()->assertSee('Tax invoice');
    }

    public function test_stripe_webhook_signature(): void
    {
        $tenant = $this->makeTenant('acme');
        $plan = Plan::query()->where('slug', 'business')->firstOrFail();
        $sub = Subscription::query()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'gateway' => 'stripe', 'interval' => 'month', 'status' => 'pending']);

        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['subscription' => 'sub_S1', 'customer' => 'cus_1', 'client_reference_id' => (string) $sub->id]]]);
        $t = time();

        $this->call('POST', 'http://saas.test/billing/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=bad"], $payload)->assertStatus(400);
        $this->call('POST', 'http://saas.test/billing/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, 'whsec_stripe'),
        ], $payload)->assertOk();

        $this->assertSame('sub_S1', $sub->fresh()->gateway_id);
        $this->assertSame('active', $sub->fresh()->status);
    }

    public function test_make_module_reaches_every_tenant(): void
    {
        $tenant = $this->makeTenant('acme', 'starter');
        $path = app_path('Modules/SaasWidgets');
        File::deleteDirectory($path);
        $neon = base_path('phpstan.neon');
        $neonBefore = File::get($neon);

        try {
            $this->artisan('make:module', ['name' => 'SaasWidget', '--fields' => 'name:string', '--migrate' => true])->assertSuccessful();

            $json = json_decode((string) file_get_contents($path.'/module.json'), true);
            $this->assertFalse($json['central']);
            $this->assertTrue($json['tenant']);

            tenancy()->initialize($tenant);
            $this->assertTrue(Schema::hasTable('saas_widgets'), 'table created in the tenant');
            $this->assertTrue(Permission::query()->where('name', 'saas-widgets.view')->exists());
            $this->assertFalse(module_enabled('saas-widgets'), 'not in the Starter plan yet');
            $tenant->update(['extra_modules' => ['saas-widgets']]);
            $this->assertTrue(module_enabled('saas-widgets'));
            tenancy()->end();
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            File::deleteDirectory($path);
            File::put($neon, $neonBefore);
        }
    }

    public function test_expired_trial_is_suspended_after_grace(): void
    {
        $tenant = $this->makeTenant('acme');
        $tenant->update(['trial_ends_at' => now()->subDays(config('starter.tenancy.grace_days') + 1)]);

        $this->artisan('saas:check-subscriptions')->assertSuccessful();
        $this->assertSame('suspended', $tenant->fresh()->status);

        $this->artisan('saas:collect-usage', ['--tenant' => 'acme'])->assertSuccessful();
        $this->assertDatabaseHas('tenant_usage', ['tenant_id' => 'acme', 'users' => 1]);
    }
}
