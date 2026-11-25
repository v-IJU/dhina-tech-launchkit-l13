<?php

declare(strict_types=1);

namespace App\Modules\Saas\Support;

use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Creating a tenant, the same way from the signup page, the console ("Onboard tenant") and tenancy:convert.
 *
 * Creating the Tenant row fires the TenancyServiceProvider pipeline:
 * new database → migrations → seeders → owner account. Takes a few seconds.
 */
class TenantProvisioner
{
    /**
     * @param  array{name: string, subdomain: string, owner_name: string, owner_email: string, password?: string|null,
     *               plan_id?: int|null, trial_days?: int|null, status?: string|null, with_demo?: bool, gstin?: string|null,
     *               database?: string|null, notes?: string|null, domain?: string|null}  $data
     */
    public function create(array $data): Tenant
    {
        $plan = isset($data['plan_id']) ? Plan::query()->find($data['plan_id']) : null;
        $trialDays = $data['trial_days'] ?? $plan->trial_days ?? 14;
        $status = $data['status'] ?? ($trialDays > 0 ? 'trial' : 'active');

        $attributes = [
            'id' => Str::lower($data['subdomain']),
            'name' => $data['name'],
            'email' => Str::lower($data['owner_email']),
            'plan_id' => $plan?->id,
            'status' => $status,
            'trial_ends_at' => $status === 'trial' ? now()->addDays($trialDays) : null,
            'owner_name' => $data['owner_name'],
            'owner_email' => Str::lower($data['owner_email']),
            'owner_password' => ! empty($data['password']) ? Hash::make($data['password']) : null,
            'with_demo' => (bool) ($data['with_demo'] ?? false),
            'gstin' => $data['gstin'] ?? null,
            'notes' => $data['notes'] ?? null,
            'converted' => ! empty($data['database']),
        ];

        // tenancy:convert: reuse an existing database instead of creating one.
        if (! empty($data['database'])) {
            $attributes['tenancy_db_name'] = $data['database'];
            $attributes['tenancy_create_database'] = false;
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->create($attributes);
        $tenant->domains()->create(['domain' => $tenant->id]);
        if (! empty($data['domain'])) {
            $tenant->domains()->create(['domain' => Str::lower($data['domain'])]);
        }

        return $tenant->refresh();
    }

    /**
     * Rules for a new subdomain: letters, numbers and dashes, not reserved, not taken.
     *
     * @return array<int, mixed>
     */
    public static function subdomainRules(): array
    {
        return [
            'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
            Rule::notIn(config('starter.tenancy.reserved_subdomains', [])),
            Rule::unique(Tenant::class, 'id'),
            Rule::unique('domains', 'domain'),
        ];
    }
}
