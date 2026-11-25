<?php

declare(strict_types=1);

namespace App\Modules\Saas\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Http\Controllers\Controller;
use App\Modules\Saas\Billing\SubscriptionService;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * SaaS > Subscriptions, plus "Record payment" (bank transfer etc.) and "Cancel" from a tenant's page.
 */
class SubscriptionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:saas.view', only: ['index']),
            new Middleware('permission:saas.edit', only: ['recordPayment', 'cancel']),
        ];
    }

    public function index(Request $request): Response
    {
        return DataTable::of(Subscription::query()->with(['tenant', 'plan']), $request)
            ->search(fn ($query, string $term) => $query->where('gateway_id', 'like', "%{$term}%")->orWhereHas('tenant', fn ($t) => $t->search($term)))
            ->sortable(['created_at', 'current_period_end', 'status'], default: 'created_at')
            ->status('status', Subscription::STATUSES)
            ->filter('gateway', 'Gateway', Subscription::GATEWAYS)
            ->filter('plan', 'Plan', Plan::query()->pluck('name', 'id')->all(), fn ($query, $value) => $query->where('plan_id', $value))
            ->export('subscriptions', [
                'ID' => 'id', 'Tenant' => 'tenant.name', 'Plan' => 'plan.name', 'Gateway' => 'gateway', 'Interval' => 'interval',
                'Status' => 'status', 'Gateway ID' => 'gateway_id', 'Renews / ends' => 'current_period_end', 'Started' => 'created_at',
            ], 'saas.export')
            ->render('saas::admin.subscriptions.index', 'saas::admin.subscriptions._table');
    }

    public function recordPayment(Request $request, Tenant $tenant, SubscriptionService $service): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists(Plan::class, 'id')],
            'interval' => ['required', Rule::in(['month', 'year'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($data['plan_id']);
        $invoice = $service->recordManualPayment($tenant, $plan, $data['interval'], Money::toMinor($data['amount']), $data['reference'] ?? null);

        return back()->with('success', "Payment recorded: invoice {$invoice->number} ({$invoice->money()}). {$tenant->name} is active on {$plan->name}.");
    }

    public function cancel(Request $request, Subscription $subscription, SubscriptionService $service): RedirectResponse
    {
        $service->cancel($subscription, $request->boolean('at_period_end', true));

        return back()->with('success', 'Subscription cancelled.');
    }
}
