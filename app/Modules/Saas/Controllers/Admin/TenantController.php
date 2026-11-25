<?php

declare(strict_types=1);

namespace App\Modules\Saas\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Saas\Billing\SubscriptionService;
use App\Modules\Saas\Models\Domain;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Support\TenantProvisioner;
use App\Modules\Saas\Support\UsageCollector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * SaaS > Tenants: list, onboard (creates the database and the owner's account), edit plan / limits / modules,
 * domains, suspend, extend trial, sign in as the tenant's owner, delete (drops the database).
 */
class TenantController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:saas.view', only: ['index', 'show']),
            new Middleware('permission:saas.create', only: ['create', 'store']),
            new Middleware('permission:saas.edit', only: ['edit', 'update', 'toggleSuspend', 'extendTrial', 'impersonate', 'addDomain', 'removeDomain', 'refreshUsage']),
            new Middleware('permission:saas.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): Response
    {
        return DataTable::of(Tenant::query()->with(['plan', 'domains', 'currentSubscription']), $request)
            ->search(fn ($query, string $term) => $query->search($term))
            ->sortable(['name', 'status', 'trial_ends_at', 'created_at'], default: 'created_at')
            ->status('status', Tenant::STATUSES)
            ->filter('plan', 'Plan', Plan::query()->orderBy('sort_order')->pluck('name', 'id')->all(), fn ($query, $value) => $query->where('plan_id', $value))
            ->dateRange('created_at', 'Signed up')
            ->export('tenants', [
                'ID' => 'id', 'Name' => 'name', 'Email' => 'email', 'Plan' => 'plan.name', 'Status' => 'status',
                'Domain' => fn (Tenant $t) => $t->primaryDomain(), 'Trial ends' => 'trial_ends_at', 'Signed up' => 'created_at',
            ], 'saas.export')
            ->render('saas::admin.tenants.index', 'saas::admin.tenants._table');
    }

    public function create(): View
    {
        return view('saas::admin.tenants.create', ['plans' => Plan::query()->available()->get()]);
    }

    /**
     * Onboard a tenant for a client: database, owner account and "choose your password" email.
     */
    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $request->merge(['subdomain' => strtolower(trim((string) $request->input('subdomain')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subdomain' => TenantProvisioner::subdomainRules(),
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:255'],
            'plan_id' => ['required', Rule::exists(Plan::class, 'id')],
            'trial_days' => ['nullable', 'integer', 'between:0,365'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'with_demo' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'string', Password::defaults()],
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/', Rule::unique('domains', 'domain'), Rule::notIn(config('tenancy.central_domains'))],
        ]);

        $tenant = $provisioner->create(['with_demo' => $request->boolean('with_demo'), 'domain' => isset($data['domain']) ? strtolower($data['domain']) : null] + $data);

        return redirect()->route('admin.saas.tenants.show', $tenant)
            ->with('success', "{$tenant->name} is ready at {$tenant->primaryDomain()}. ".(empty($data['password'])
                ? "{$data['owner_email']} got an email to choose a password."
                : 'Hand the password to the owner.'));
    }

    public function show(Tenant $tenant, ModuleManager $modules): View
    {
        $tenant->load(['plan', 'domains', 'subscriptions.plan', 'invoices']);

        return view('saas::admin.tenants.show', [
            'tenant' => $tenant,
            'usage' => $tenant->usage()->limit(30)->get(),
            'plans' => Plan::query()->available()->get(),
            'modules' => $modules->planModules(),
            'owner' => rescue(fn () => $this->owner($tenant), null, false),
        ]);
    }

    public function edit(Tenant $tenant, ModuleManager $modules): View
    {
        return view('saas::admin.tenants.edit', [
            'tenant' => $tenant,
            'plans' => Plan::query()->orderBy('sort_order')->get(),
            'modules' => $modules->planModules(),
        ]);
    }

    /**
     * The tenant's first super admin as plain values: a User model would keep talking to the
     * tenant connection after $tenant->run() has switched back.
     *
     * @return object{name: string, email: string, last_login_at: Carbon|null}|null
     */
    private function owner(Tenant $tenant): ?object
    {
        $row = $tenant->run(fn () => User::query()->role(config('starter.super_admin_role'))->oldest('id')->first(['users.id', 'users.name', 'users.email', 'users.last_login_at'])?->only(['name', 'email', 'last_login_at']));

        return $row ? (object) $row : null;
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'plan_id' => ['nullable', Rule::exists(Plan::class, 'id')],
            'status' => ['required', Rule::in(array_keys(Tenant::STATUSES))],
            'trial_ends_at' => ['nullable', 'date'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'extra_modules' => ['array'],
            'extra_modules.*' => ['string', 'max:50'],
            'limit_overrides' => ['array'],
            'limit_overrides.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $tenant->fill([
            'name' => $data['name'], 'email' => strtolower($data['email']), 'plan_id' => $data['plan_id'] ?? null,
            'status' => $data['status'], 'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'suspended_at' => in_array($data['status'], ['suspended', 'cancelled'], true) ? ($tenant->suspended_at ?? now()) : null,
        ]);
        $tenant->gstin = $data['gstin'] ?? null;
        $tenant->billing_address = $data['billing_address'] ?? null;
        $tenant->notes = $data['notes'] ?? null;
        $tenant->extra_modules = array_values($data['extra_modules'] ?? []);
        $tenant->limit_overrides = array_filter($data['limit_overrides'] ?? [], fn ($v) => $v !== null && $v !== '');
        $tenant->save();

        return redirect()->route('admin.saas.tenants.show', $tenant)->with('success', "{$tenant->name} updated.");
    }

    public function toggleSuspend(Tenant $tenant, SubscriptionService $service): RedirectResponse
    {
        if ($tenant->isBlocked()) {
            $service->reactivate($tenant);

            return back()->with('success', "{$tenant->name} is active again.");
        }

        $service->suspend($tenant, 'Suspended by staff.');

        return back()->with('success', "{$tenant->name} is suspended. Only sign-in and Billing work for them.");
    }

    public function extendTrial(Request $request, Tenant $tenant): RedirectResponse
    {
        $days = $request->validate(['days' => ['required', 'integer', 'between:1,365']])['days'];
        $from = $tenant->trial_ends_at?->isFuture() ? $tenant->trial_ends_at : now();
        $tenant->forceFill(['status' => 'trial', 'trial_ends_at' => $from->copy()->addDays($days), 'suspended_at' => null])->save();

        return back()->with('success', "Trial extended to {$tenant->trial_ends_at?->format('d M Y')}.");
    }

    /**
     * Open the tenant's admin panel signed in as its owner (one-time link, 60 seconds).
     */
    public function impersonate(Tenant $tenant): RedirectResponse
    {
        $ownerId = $tenant->run(fn () => User::query()->role(config('starter.super_admin_role'))->oldest('id')->value('id'));
        abort_unless($ownerId, 404, 'This tenant has no owner account.');

        $token = ImpersonationToken::query()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'user_id' => (string) $ownerId,
            'redirect_url' => '/'.trim((string) config('starter.admin_path'), '/'),
            'auth_guard' => 'web',
        ]);
        activity('saas')->performedOn($tenant)->event('impersonate')->log("Signed in to {$tenant->name} as its owner");

        return redirect()->away($tenant->url('impersonate/'.$token->token));
    }

    public function addDomain(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->merge(['domain' => strtolower(trim((string) $request->input('domain')))]);
        $domain = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/', Rule::unique('domains', 'domain'), Rule::notIn(config('tenancy.central_domains'))],
        ])['domain'];

        $tenant->domains()->create(['domain' => $domain]);

        return back()->with('success', "{$domain} added. Point its DNS (CNAME) to ".config('tenancy.central_domains.0').' and make sure HTTPS covers it (SAAS.md).');
    }

    public function removeDomain(Tenant $tenant, Domain $domain): RedirectResponse
    {
        abort_unless($domain->tenant_id === $tenant->id, 404);
        abort_if($tenant->domains()->count() === 1, 422, 'A tenant needs at least one domain.');
        $domain->delete();

        return back()->with('success', 'Domain removed.');
    }

    public function refreshUsage(Tenant $tenant, UsageCollector $collector): RedirectResponse
    {
        $collector->collect($tenant);

        return back()->with('success', 'Usage measured.');
    }

    /**
     * Deletes the tenant AND its database. Typing the tenant id confirms it.
     */
    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate(['confirm' => ['required', Rule::in([$tenant->id])]], ['confirm.in' => 'Type the tenant ID exactly to confirm.']);

        $tenant->subscriptions()->whereIn('status', ['active', 'past_due', 'cancelled'])->get()
            ->each(fn ($s) => rescue(fn () => app(SubscriptionService::class)->cancel($s, false), report: false));
        $name = $tenant->name;
        $tenant->delete();
        activity('saas')->event('deleted')->log("Deleted tenant {$name} and its database");

        return redirect()->route('admin.saas.tenants.index')->with('success', "{$name} and its database were deleted.");
    }
}
