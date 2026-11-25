<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Settings\SettingsRepository;
use App\Models\User;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * php artisan tenancy:convert on a real (SQLite file) single-business database.
 * Runs in a temp folder: its own .env, database/ and storage/.
 */
class ConvertToTenancyTest extends TestCase
{
    private string $dir;

    private string $legacy;

    private string $central;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();

        $this->dir = sys_get_temp_dir().'/convert-'.uniqid();
        $this->legacy = database_path('convert_legacy_'.uniqid().'.sqlite');
        $this->central = database_path('convert_central_'.uniqid().'.sqlite');
        (new Filesystem)->ensureDirectoryExists($this->dir.'/storage/app/public/media/1');
        file_put_contents($this->dir.'/storage/app/public/media/1/logo.png', 'png');
        file_put_contents($this->dir.'/.env', "APP_NAME=Test\nDB_CONNECTION=sqlite\nDB_DATABASE={$this->legacy}\n");
        touch($this->legacy);

        $this->app->useStoragePath($this->dir.'/storage');
        $this->app->useEnvironmentPath($this->dir);
        config(['database.connections.sqlite.database' => $this->legacy, 'starter.tenancy.enabled' => false]);
        DB::purge('sqlite');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        DB::purge('sqlite');
        (new Filesystem)->deleteDirectory($this->dir);
        @unlink($this->legacy);
        @unlink($this->central);
        parent::tearDown();
    }

    public function test_existing_install_becomes_the_first_tenant(): void
    {
        // A single-business install with data.
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(DatabaseSeeder::class);
        app(SettingsRepository::class)->set(['site_name' => 'Old Biz']);
        $admin = User::query()->create(['name' => 'Viju', 'first_name' => 'Viju', 'last_name' => 'K', 'username' => 'viju', 'email' => 'viju@oldbiz.com', 'password' => Hash::make('Secret#123'), 'status' => 'active']);
        $admin->assignRole('super-admin');
        User::query()->create(['name' => 'Staff', 'first_name' => 'S', 'last_name' => 'T', 'username' => 'staff', 'email' => 'staff@oldbiz.com', 'password' => Hash::make('Secret#123'), 'status' => 'active']);

        $this->artisan('tenancy:convert', [
            '--subdomain' => 'oldbiz', '--central-domain' => 'biz.test', '--central-database' => $this->central,
            '--domain' => 'www.oldbiz.com', '--plan' => 'business', '--force' => true,
        ])->assertSuccessful();

        // .env switched to the central database.
        $env = (string) file_get_contents($this->dir.'/.env');
        $this->assertStringContainsString('STARTER_TENANCY=true', $env);
        $this->assertStringContainsString('CENTRAL_DOMAIN=biz.test', $env);
        $this->assertStringContainsString('DB_DATABASE='.$this->central, $env);

        // Central: plans, the tenant, the super admin (same password), no staff.
        $this->assertSame(3, Plan::query()->count());
        $tenant = Tenant::query()->findOrFail('oldbiz');
        $this->assertSame('active', $tenant->status);
        $this->assertSame('Old Biz', $tenant->name);
        $this->assertSame('business', $tenant->plan->slug);
        $this->assertEqualsCanonicalizing(['oldbiz', 'www.oldbiz.com'], $tenant->domains->pluck('domain')->all());
        $this->assertTrue(Hash::check('Secret#123', User::query()->where('email', 'viju@oldbiz.com')->value('password')));
        $this->assertNull(User::query()->where('email', 'staff@oldbiz.com')->first());

        // Tenant: the old database, untouched.
        tenancy()->initialize($tenant);
        $this->assertSame('Old Biz', setting('site_name'));
        $this->assertSame(2, User::query()->count());
        $this->assertTrue(Hash::check('Secret#123', User::query()->where('email', 'viju@oldbiz.com')->value('password')));
        tenancy()->end();

        // Files moved into the tenant folder; the old path still resolves.
        $this->assertFileExists($this->dir.'/storage/app/public/tenantoldbiz/media/1/logo.png');
        $this->assertTrue(is_link($this->dir.'/storage/app/public/media'));
        $this->assertFileExists($this->dir.'/storage/app/public/media/1/logo.png');

        // Running it again is refused.
        $this->artisan('tenancy:convert', ['--subdomain' => 'x', '--central-domain' => 'biz.test', '--central-database' => 'y', '--force' => true])->assertFailed();
    }
}
