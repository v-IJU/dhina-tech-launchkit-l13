<?php

namespace Database\Seeders;

use App\Core\Settings\SettingsRepository;
use App\Core\Tenancy\Tenancy;
use Illuminate\Database\Seeder;

/**
 * SaaS mode: runs inside every NEW tenant database (config/tenancy.php → seeder_parameters).
 * Same base data as a single-business install (roles, permissions, menus, settings),
 * with the site name and email taken from the tenant (not for a converted install: its settings stay). Demo pages/posts if "with_demo" was ticked.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $tenant = Tenancy::tenant();
        if ($tenant && ! $tenant->converted) {
            app(SettingsRepository::class)->set(array_filter([
                'site_name' => $tenant->name,
                'contact_email' => $tenant->email,
            ]));

            if ($tenant->with_demo) {
                $this->call(DemoContentSeeder::class);
            }
        }
    }
}
