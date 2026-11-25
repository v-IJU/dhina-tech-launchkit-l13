<?php

declare(strict_types=1);

namespace App\Modules\Saas\Jobs;

use App\Models\User;
use App\Modules\Saas\Models\Tenant;
use App\Notifications\WelcomeUser;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Last step of creating a tenant (TenancyServiceProvider pipeline): the owner's account
 * in the tenant's own database, with the super-admin role OF THAT TENANT.
 *
 *  - signup:   the password they chose (stored hashed in tenant data only until now, then removed)
 *  - onboard:  no password → "Choose your password" email with a link to their own domain
 */
class CreateTenantOwner
{
    public function __construct(private readonly Tenant $tenant) {}

    public function handle(): void
    {
        $tenant = $this->tenant;

        // tenancy:convert: the existing database already has its users and admins.
        if ($tenant->converted) {
            return;
        }
        $email = strtolower((string) ($tenant->owner_email ?: $tenant->email));
        $hashed = $tenant->owner_password;

        $tenant->run(function () use ($tenant, $email, $hashed) {
            $name = trim((string) ($tenant->owner_name ?: Str::before($email, '@')));
            [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');

            $user = User::query()->firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $name,
                'first_name' => $first,
                'last_name' => $last ?: $first,
                'username' => Str::slug(Str::before($email, '@')).'-'.Str::lower(Str::random(4)),
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => $hashed ?: Str::password(32),
            ])->save();
            $user->syncRoles([config('starter.super_admin_role')]);

            if (! $hashed) {
                $user->notify(new WelcomeUser(Password::broker('users')->createToken($user)));
            }
        });

        // Never keep the password hash in the central database.
        $tenant->owner_password = null;
        $tenant->save();
    }
}
