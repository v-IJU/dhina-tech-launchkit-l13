<?php

declare(strict_types=1);

namespace App\Modules\Saas\Console;

use App\Core\Support\EnvFile;
use App\Models\User;
use App\Modules\Saas\Database\Seeders\PlanSeeder;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Support\TenantProvisioner;
use Illuminate\Cache\TaggableStore;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * Turn an existing single-business install into SaaS mode. Your current database becomes
 * the FIRST TENANT, untouched (users, content, settings, roles stay exactly as they are).
 * A new, empty central database holds plans, tenants, subscriptions and invoices.
 *
 *   php artisan tenancy:convert --subdomain=acme --central-domain=myapp.com --central-database=starter_central
 *
 * What it does, in order:
 *   1. creates the central database and switches .env to it (DB_DATABASE, STARTER_TENANCY, CENTRAL_DOMAIN)
 *   2. migrates + seeds the central database (roles, menus, settings, the 3 default plans)
 *   3. copies your super admins into the central database (same email + password) for the SaaS console
 *   4. registers the old database as tenant "acme" (acme.myapp.com), status active, on the plan you pick
 *   5. moves uploaded files into the tenant's folder (storage/app/public/tenant{id}/ or uploads/tenant{id}/ on S3)
 *
 * Take a database backup first (php artisan backup:run). There is no automatic way back.
 */
class ConvertToTenancyCommand extends Command
{
    protected $signature = 'tenancy:convert
        {--subdomain= : Subdomain of the first tenant (acme → acme.myapp.com)}
        {--central-domain= : Main domain of the SaaS (signup, pricing, console)}
        {--central-database= : Name of the NEW central database (created if missing)}
        {--domain= : The tenant\'s existing custom domain (e.g. www.acme.com), optional}
        {--name= : Tenant company name (default: the Site name setting)}
        {--plan= : Plan code for the tenant (default: the most complete plan)}
        {--skip-files : Do not move uploaded files}
        {--force : No questions}';

    protected $description = 'Convert this single-business install to SaaS mode; the current database becomes the first tenant';

    public function handle(TenantProvisioner $provisioner): int
    {
        if (config('starter.tenancy.enabled') && Tenant::query()->exists()) {
            $this->components->error('This install is already in SaaS mode.');

            return self::FAILURE;
        }

        if (! Cache::getStore() instanceof TaggableStore) {
            $this->components->error('SaaS mode needs a cache store with tags. Set CACHE_STORE=redis in .env first (the Docker setup has Redis).');

            return self::FAILURE;
        }

        $connection = (string) config('database.default');
        $legacy = (string) config("database.connections.{$connection}.database");
        $driver = (string) config("database.connections.{$connection}.driver");

        $subdomain = Str::lower((string) ($this->option('subdomain') ?: text('Subdomain for your current business', placeholder: 'acme', required: true)));
        $centralDomain = Str::lower((string) ($this->option('central-domain') ?: text('Main SaaS domain', default: (string) parse_url((string) config('app.url'), PHP_URL_HOST), required: true)));
        $central = (string) ($this->option('central-database') ?: text('New central database name', default: $driver === 'sqlite' ? database_path('central.sqlite') : $legacy.'_central', required: true));
        $customDomain = $this->option('domain') ? Str::lower((string) $this->option('domain')) : null;

        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $subdomain) || in_array($subdomain, config('starter.tenancy.reserved_subdomains', []), true)) {
            $this->components->error("\"{$subdomain}\" can't be used as a subdomain.");

            return self::FAILURE;
        }
        // stancl/tenancy keeps SQLite tenant files in database/, by file name.
        $tenantDatabase = $legacy;
        if ($driver === 'sqlite') {
            $dir = rtrim(database_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (! str_starts_with($legacy, $dir)) {
                $this->components->error("Move the SQLite file into {$dir} first.");

                return self::FAILURE;
            }
            $tenantDatabase = substr($legacy, strlen($dir));
        }

        if ($central === $legacy) {
            $this->components->error('The central database must be a NEW database, not the current one.');

            return self::FAILURE;
        }

        // Read what we need from the current database before switching.
        $name = (string) ($this->option('name') ?: DB::table('settings')->where('key', 'site_name')->value('value') ?: config('app.name'));
        $name = trim((string) (json_decode($name, true) ?? $name)) ?: (string) config('app.name');
        $admins = $this->superAdmins();
        if ($admins === []) {
            $this->components->error('No active super admin found in the current database.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Current database', "{$legacy} → becomes tenant <info>{$subdomain}</info> ({$subdomain}.{$centralDomain})");
        $this->components->twoColumnDetail('New central database', $central);
        $this->components->twoColumnDetail('Central domain', $centralDomain);
        $this->components->twoColumnDetail('Console admins', implode(', ', array_column($admins, 'email')));
        if (! $this->option('force') && $this->input->isInteractive() && ! confirm('Have you taken a backup, and do you want to continue?', default: false)) {
            return self::FAILURE;
        }

        // 1. Central database + .env
        $this->components->task('Creating the central database', fn () => $this->createDatabase($connection, $driver, $central));
        $written = EnvFile::set(['DB_DATABASE' => $central, 'STARTER_TENANCY' => 'true', 'CENTRAL_DOMAIN' => $centralDomain]);
        if (! $written) {
            $this->components->warn("Could not write .env. Set these yourself: DB_DATABASE={$central}  STARTER_TENANCY=true  CENTRAL_DOMAIN={$centralDomain}");
        }
        config([
            "database.connections.{$connection}.database" => $central,
            'starter.tenancy.enabled' => true,
            'tenancy.central_domains' => array_values(array_unique([$centralDomain, ...array_slice((array) config('tenancy.central_domains'), 1)])),
        ]);
        DB::purge($connection);
        DB::reconnect($connection);

        // 2. Central schema and base data
        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--force' => true]);
        $this->call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);

        // 3. Super admins → central console
        $this->components->task('Copying super admins to the console', function () use ($admins) {
            foreach ($admins as $admin) {
                $id = DB::table('users')->where('email', $admin['email'])->value('id') ?? DB::table('users')->insertGetId($admin + ['created_at' => now(), 'updated_at' => now()]);
                User::query()->findOrFail($id)->syncRoles([config('starter.super_admin_role')]);
            }
        });

        // 4. Register the old database as the first tenant
        $plan = Plan::query()->where('slug', (string) $this->option('plan'))->first()
            ?? Plan::query()->orderByDesc('price_monthly')->first();

        $tenant = null;
        $this->components->task("Registering tenant {$subdomain}", function () use ($provisioner, $subdomain, $name, $admins, $plan, $tenantDatabase, $customDomain, &$tenant) {
            $tenant = $provisioner->create([
                'name' => $name,
                'subdomain' => $subdomain,
                'owner_name' => $admins[0]['name'],
                'owner_email' => $admins[0]['email'],
                'plan_id' => $plan?->id,
                'status' => 'active',
                'trial_days' => 0,
                'database' => $tenantDatabase,
                'domain' => $customDomain,
                'notes' => 'Converted from the single-business install on '.now()->format('d M Y').'.',
            ]);
        });

        // Menus and permissions of the new modules (Billing…) inside the tenant.
        $tenant?->run(fn () => $this->callSilently('module:sync'));

        // 5. Files
        if (! $this->option('skip-files') && $tenant) {
            $this->moveFiles($tenant);
        }

        $this->call('optimize:clear');

        $this->newLine();
        $this->components->info('Converted. Next steps:');
        $this->line("  • SaaS console:     https://{$centralDomain}/".trim((string) config('starter.admin_path'), '/').'  (same admin email + password)');
        $this->line('  • Your business:    '.$tenant?->url(trim((string) config('starter.admin_path'), '/')));
        if ($customDomain) {
            $this->line("  • Custom domain:    {$customDomain} keeps working once it points at this server");
        }
        $this->line("  • DNS:              add a wildcard A record *.{$centralDomain} → this server, and a wildcard SSL certificate (see SAAS.md)");
        $this->line('  • Restart workers:  php artisan horizon:terminate (or restart the queue / Docker app container)');

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function superAdmins(): array
    {
        return DB::table('users')
            ->join('model_has_roles', fn ($j) => $j->on('model_has_roles.model_id', '=', 'users.id')->where('model_has_roles.model_type', User::class))
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', config('starter.super_admin_role'))
            ->whereNull('users.deleted_at')
            ->select(['users.name', 'users.first_name', 'users.last_name', 'users.username', 'users.email', 'users.password', 'users.status', 'users.email_verified_at'])
            ->orderBy('users.id')
            ->get()
            ->map(fn ($u) => (array) $u)
            ->all();
    }

    private function createDatabase(string $connection, string $driver, string $name): void
    {
        if ($driver === 'sqlite') {
            if (! is_file($name)) {
                touch($name);
            }

            return;
        }

        $quoted = $driver === 'pgsql' ? '"'.str_replace('"', '', $name).'"' : '`'.str_replace('`', '', $name).'`';
        $exists = $driver === 'pgsql'
            ? DB::connection($connection)->selectOne('select 1 from pg_database where datname = ?', [$name])
            : DB::connection($connection)->selectOne('select schema_name from information_schema.schemata where schema_name = ?', [$name]);

        if (! $exists) {
            $charset = (string) config("database.connections.{$connection}.charset", 'utf8mb4');
            $collation = (string) config("database.connections.{$connection}.collation", 'utf8mb4_unicode_ci');
            DB::connection($connection)->statement($driver === 'pgsql'
                ? "create database {$quoted}"
                : "create database {$quoted} character set {$charset} collate {$collation}");
        }
    }

    /**
     * Uploaded files move into the tenant's folder, because in SaaS mode every disk is
     * suffixed with "tenant{id}" (config/tenancy.php → filesystem).
     */
    private function moveFiles(Tenant $tenant): void
    {
        $folder = config('tenancy.filesystem.suffix_base', 'tenant').$tenant->getTenantKey();

        $this->components->task("Moving uploaded files to {$folder}/", function () use ($folder) {
            $files = new Filesystem;
            foreach (array_unique([storage_path('app/public'), storage_path('app/private')]) as $root) {
                if (! is_dir($root)) {
                    continue;
                }
                $target = $root.DIRECTORY_SEPARATOR.$folder;
                $files->ensureDirectoryExists($target);
                foreach (array_merge($files->directories($root), $files->files($root, true)) as $item) {
                    $item = (string) $item;
                    $base = basename($item);
                    if ($base === $folder || str_starts_with($base, 'tenant') || $base === '.gitignore' || is_link($item) || ($base === 'backups' && str_ends_with($root, 'private'))) {
                        continue;
                    }
                    $files->move($item, $target.DIRECTORY_SEPARATOR.$base);

                    // Old /storage/... links pasted into page or post content keep working.
                    if (str_ends_with($root, 'public')) {
                        @symlink($folder.DIRECTORY_SEPARATOR.$base, $item);
                    }
                }
            }
        });

        if (config('filesystems.disks.uploads.driver') === 's3') {
            $this->components->task("Copying S3 uploads to {$folder}/ (originals are kept)", function () use ($folder) {
                $base = (array) config('filesystems.disks.uploads');
                $from = Storage::build($base);
                $to = Storage::build(['root' => trim(($base['root'] ?? '').'/'.$folder, '/')] + $base);
                foreach ($from->allFiles() as $path) {
                    if (str_starts_with($path, $folder.'/') || str_starts_with($path, 'tenant')) {
                        continue;
                    }
                    try {
                        $to->writeStream($path, $from->readStream($path), ['visibility' => $from->getVisibility($path)]);
                    } catch (Throwable $e) {
                        $this->components->warn("{$path}: {$e->getMessage()}");
                    }
                }
            });
        }
    }
}
