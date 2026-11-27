<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Support\EnvFile;
use App\Models\User;
use App\Modules\Saas\Database\Seeders\PlanSeeder;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Support\TenantProvisioner;
use Illuminate\Cache\TaggableStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * One-time setup: migrate, seed roles / permissions / menus / settings /
 * demo content, link storage and create the first super-admin.
 *
 * Asks how the install is used:
 *   single business  one company, one database (the default, what you had before)
 *   SaaS             many companies (tenants), a database each, plans + billing (see SAAS.md)
 *
 *   php artisan starter:install
 *   php artisan starter:install --email=admin@example.com --password=Secret#123 --no-interaction
 *   php artisan starter:install --mode=saas --central-domain=myapp.com --demo-tenant=demo
 *
 * Single business → SaaS later: php artisan tenancy:convert. SaaS → single business is not supported.
 */
class StarterInstallCommand extends Command
{
    protected $signature = 'starter:install
        {--name=Super Admin : Name of the first admin}
        {--email= : Email of the first admin}
        {--password= : Password of the first admin}
        {--fresh : Drop all tables first (destroys data)}
        {--no-demo : Skip demo website content}
        {--mode= : single or saas (asked when empty)}
        {--central-domain= : SaaS mode: the main domain (signup, pricing, console)}
        {--demo-tenant= : SaaS mode: also create a demo tenant with this subdomain}';

    protected $description = 'Install the starter: database, roles, permissions, menus, settings and first admin user';

    public function handle(): int
    {
        $this->components->info('Installing Laravel Starter');

        if (! $this->chooseMode()) {
            return self::FAILURE;
        }
        $saas = (bool) config('starter.tenancy.enabled');

        if ($this->option('fresh')) {
            if ($this->laravel->environment(['production', 'staging']) && ! $this->confirm('This deletes ALL data on this '.$this->laravel->environment().' server. Continue?')) {
                return self::FAILURE;
            }
            $this->call('migrate:fresh', ['--force' => true]);
        } else {
            $this->call('migrate', ['--force' => true]);
        }

        $this->call('db:seed', ['--force' => true]);
        if ($saas) {
            $this->call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
        }
        if (! $this->option('no-demo')) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoContentSeeder', '--force' => true]);
        }

        if (! file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        $this->createSuperAdmin();

        if ($saas && $this->option('demo-tenant')) {
            $this->createDemoTenant((string) $this->option('demo-tenant'));
        }

        $this->newLine();
        $this->components->info('Done. Sign in at '.url(config('starter.admin_path').'/login'));
        if ($saas) {
            $this->line('  SaaS console: SaaS > Overview. Pricing: '.url('pricing').'  Signup: '.url('signup'));
            $this->line('  Set up payments under Settings > Billing, and wildcard DNS *.'.config('tenancy.central_domains.0').' (SAAS.md).');
        }

        return self::SUCCESS;
    }

    /**
     * Single business or SaaS. Writes STARTER_TENANCY / CENTRAL_DOMAIN to .env for SaaS.
     */
    private function chooseMode(): bool
    {
        $mode = $this->option('mode');

        if (! $mode) {
            $mode = config('starter.tenancy.enabled') ? 'saas' : ($this->input->isInteractive()
                ? select('How will this install be used?', [
                    'single' => 'Single business: one company, one database',
                    'saas' => 'SaaS: many companies (tenants), a database each, plans and billing',
                ], default: 'single')
                : 'single');
        }

        if ($mode !== 'saas') {
            return true;
        }

        if (! Cache::getStore() instanceof TaggableStore) {
            $this->components->error('SaaS mode needs a cache store with tags. Set CACHE_STORE=redis in .env (the Docker setup has Redis) and run again.');

            return false;
        }

        $domain = strtolower((string) ($this->option('central-domain') ?: ($this->input->isInteractive()
            ? text('Main SaaS domain (tenants get <name>.<domain>)', default: (string) parse_url((string) config('app.url'), PHP_URL_HOST), required: true)
            : parse_url((string) config('app.url'), PHP_URL_HOST))));

        if (! EnvFile::set(['STARTER_TENANCY' => 'true', 'CENTRAL_DOMAIN' => $domain])) {
            $this->components->warn("Could not write .env. Add: STARTER_TENANCY=true  CENTRAL_DOMAIN={$domain}");
        }
        config([
            'starter.tenancy.enabled' => true,
            'tenancy.central_domains' => array_values(array_unique([$domain, ...array_slice((array) config('tenancy.central_domains'), 1)])),
        ]);
        $this->components->info("SaaS mode on. Central domain: {$domain}. The database user needs CREATE DATABASE rights.");

        return true;
    }

    private function createDemoTenant(string $subdomain): void
    {
        $this->components->task("Creating demo tenant {$subdomain}", function () use ($subdomain) {
            app(TenantProvisioner::class)->create([
                'name' => 'Demo Company',
                'subdomain' => $subdomain,
                'owner_name' => 'Demo Owner',
                'owner_email' => 'owner@'.$subdomain.'.test',
                'password' => 'Demo@12345',
                'plan_id' => Plan::query()->where('is_featured', true)->value('id'),
                'with_demo' => true,
            ]);
        });
        $this->line("  Demo tenant: {$subdomain}.".config('tenancy.central_domains.0').'  owner@'.$subdomain.'.test / Demo@12345');
    }

    private function createSuperAdmin(): void
    {
        $email = $this->option('email') ?: ($this->input->isInteractive()
            ? text('Admin email', default: 'admin@example.com', required: true)
            : 'admin@example.com');

        if ($existing = User::query()->where('email', $email)->first()) {
            $existing->assignRole(config('starter.super_admin_role'));
            $this->components->info("User {$email} already exists and has the super-admin role.");

            return;
        }

        $pass = $this->option('password') ?: ($this->input->isInteractive()
            ? password('Admin password (min 8 chars, mixed case, number, symbol)', required: true)
            : null);

        $generated = false;
        if (! $pass) {
            $pass = 'Admin@'.bin2hex(random_bytes(4));
            $generated = true;
        }

        $validator = Validator::make(['password' => $pass], ['password' => [Password::min(8)->letters()->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('password'));
            $this->createSuperAdmin();

            return;
        }

        $user = User::query()->create([
            'name' => (string) $this->option('name'),
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'username' => 'admin',
            'email' => $email,
            'password' => Hash::make($pass),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole(config('starter.super_admin_role'));

        $this->components->info("Super admin created: {$email}".($generated ? "  password: {$pass}  (change it after signing in)" : ''));
    }
}
