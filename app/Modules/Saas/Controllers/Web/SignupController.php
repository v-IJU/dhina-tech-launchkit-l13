<?php

declare(strict_types=1);

namespace App\Modules\Saas\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Support\TenantProvisioner;
use App\Notifications\AdminAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * /signup on the central domain: company + subdomain + owner → a new tenant on a free trial,
 * then straight to their own admin panel's sign-in page.
 */
class SignupController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless(setting('saas_signup', true), 404);

        return view('saas::web.signup', [
            'plans' => Plan::query()->available()->where('is_public', true)->get(),
            'selected' => (string) $request->query('plan', ''),
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        abort_unless(setting('saas_signup', true), 404);

        $request->merge(['subdomain' => strtolower(trim((string) $request->input('subdomain')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subdomain' => TenantProvisioner::subdomainRules(),
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'plan_id' => ['required', Rule::exists(Plan::class, 'id')->where('is_active', true)],
            'terms' => ['accepted'],
            'website' => ['prohibited'], // honeypot
        ]);

        $tenant = $provisioner->create($data);

        AdminAlert::toAdmins("New signup: {$tenant->name} ({$tenant->plan?->name}, trial)", route('admin.saas.tenants.show', $tenant), 'saas.view', 'bi-rocket-takeoff');

        return redirect()->away($tenant->url(trim((string) config('starter.admin_path'), '/').'/login?welcome=1'));
    }

    /**
     * Live "is this subdomain free?" check for the signup form.
     */
    public function check(Request $request): JsonResponse
    {
        $value = strtolower(trim((string) $request->query('subdomain')));
        $validator = Validator::make(['subdomain' => $value], ['subdomain' => TenantProvisioner::subdomainRules()]);

        return response()->json([
            'available' => ! $validator->fails(),
            'message' => $validator->errors()->first('subdomain') ?: $value.'.'.config('tenancy.central_domains.0').' is available.',
            'tenant' => Tenant::query()->whereKey($value)->exists(),
        ]);
    }
}
