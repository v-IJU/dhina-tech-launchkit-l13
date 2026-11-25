<?php

declare(strict_types=1);

namespace App\Modules\Saas\Controllers\Admin;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SaaS > Plans: prices, trial, which modules and limits a tenant gets, gateway ids, pricing page bullets.
 * Changing a plan applies to every tenant on it straight away (modules and limits are read live).
 */
class PlanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:plans.view', only: ['index']),
            new Middleware('permission:plans.create', only: ['create', 'store']),
            new Middleware('permission:plans.edit', only: ['edit', 'update']),
            new Middleware('permission:plans.delete', only: ['destroy']),
        ];
    }

    public function index(ModuleManager $modules): View
    {
        return view('saas::admin.plans.index', [
            'plans' => Plan::query()->withCount('tenants')->orderBy('sort_order')->orderBy('price_monthly')->get(),
            'moduleNames' => $modules->planModules()->map->name->all(),
        ]);
    }

    public function create(ModuleManager $modules): View
    {
        return view('saas::admin.plans.form', [
            'plan' => new Plan(['currency' => config('starter.tenancy.currency', 'INR'), 'trial_days' => 14, 'is_public' => true, 'is_active' => true, 'modules' => []]),
            'modules' => $modules->planModules(),
            'core' => $modules->enabled()->filter(fn ($m) => $m->tenant && $m->core),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::query()->create($this->validated($request));

        return redirect()->route('admin.saas.plans.index')->with('success', "Plan {$plan->name} created.");
    }

    public function edit(Plan $plan, ModuleManager $modules): View
    {
        return view('saas::admin.plans.form', [
            'plan' => $plan,
            'modules' => $modules->planModules(),
            'core' => $modules->enabled()->filter(fn ($m) => $m->tenant && $m->core),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('admin.saas.plans.index')->with('success', "Plan {$plan->name} saved. Tenants on it get the change straight away.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($count = $plan->tenants()->count()) {
            return back()->with('error', "{$count} tenant(s) are on {$plan->name}. Move them to another plan, or untick \"Can be chosen\" instead.");
        }

        $plan->delete();

        return back()->with('success', "Plan {$plan->name} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique(Plan::class, 'slug')->ignore($plan)],
            'description' => ['nullable', 'string', 'max:255'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'trial_days' => ['required', 'integer', 'between:0,365'],
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in(app(ModuleManager::class)->planModules()->keys()->all())],
            'limits' => ['array'],
            'limits.*' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'string', 'max:2000'],
            'razorpay_plan_monthly' => ['nullable', 'string', 'max:100'],
            'razorpay_plan_yearly' => ['nullable', 'string', 'max:100'],
            'stripe_price_monthly' => ['nullable', 'string', 'max:100'],
            'stripe_price_yearly' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            ...$data,
            'currency' => strtoupper($data['currency']),
            'price_monthly' => Money::toMinor($data['price_monthly']),
            'price_yearly' => Money::toMinor($data['price_yearly']),
            'modules' => array_values($data['modules'] ?? []),
            'limits' => collect(Plan::LIMITS)->keys()->mapWithKeys(fn ($k) => [$k => isset($data['limits'][$k]) && $data['limits'][$k] !== '' ? (int) $data['limits'][$k] : null])->all(),
            'features' => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($data['features'] ?? '')) ?: []))),
            'is_public' => $request->boolean('is_public'),
            'is_active' => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
