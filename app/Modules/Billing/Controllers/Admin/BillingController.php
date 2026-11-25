<?php

declare(strict_types=1);

namespace App\Modules\Billing\Controllers\Admin;

use App\Core\Tenancy\Tenancy;
use App\Http\Controllers\Controller;
use App\Modules\Saas\Billing\GatewayManager;
use App\Modules\Saas\Billing\SubscriptionService;
use App\Modules\Saas\Models\Invoice;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Support\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Admin > Billing inside a tenant: plan, trial, usage vs limits, invoices; pay or change plan.
 * The data lives in the central database (Plan, Subscription, Invoice use the central connection).
 */
class BillingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:billing.view', only: ['index', 'plans', 'returned', 'invoice']),
            new Middleware('permission:billing.manage', only: ['checkout', 'cancel']),
        ];
    }

    public function index(GatewayManager $gateways): View
    {
        $tenant = $this->tenant()->load(['plan', 'currentSubscription.plan']);

        return view('billing::admin.index', [
            'tenant' => $tenant,
            'subscription' => $tenant->currentSubscription,
            'usage' => PlanLimits::usage(),
            'invoices' => $tenant->invoices()->limit(24)->get(),
            'gateways' => $gateways->available(),
        ]);
    }

    public function plans(GatewayManager $gateways): View
    {
        return view('billing::admin.plans', [
            'tenant' => $this->tenant(),
            'plans' => Plan::query()->available()->get(),
            'gateways' => $gateways->available(),
        ]);
    }

    public function checkout(Request $request, SubscriptionService $service, GatewayManager $gateways): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists(Plan::class, 'id')->where('is_active', true)],
            'interval' => ['required', Rule::in(['month', 'year'])],
            'gateway' => ['required', Rule::in(array_keys($gateways->available()))],
        ]);

        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($data['plan_id']);

        try {
            $url = $service->startCheckout($this->tenant(), $plan, $data['interval'], $data['gateway'],
                route('admin.billing.return', ['status' => 'success']), route('admin.billing.return', ['status' => 'cancelled']));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'The payment page could not be opened. Please try again, or contact us. ('.class_basename($e).')');
        }

        return redirect()->away($url);
    }

    /**
     * Back from the payment page. The webhook does the real work; this only tells the owner what's happening.
     */
    public function returned(Request $request): RedirectResponse
    {
        return $request->query('status') === 'success'
            ? redirect()->route('admin.billing.index')->with('success', 'Thank you! Your payment is being confirmed; this page updates within a minute.')
            : redirect()->route('admin.billing.plans')->with('error', 'Payment was not completed. Nothing was charged.');
    }

    public function cancel(SubscriptionService $service): RedirectResponse
    {
        $subscription = $this->tenant()->currentSubscription;
        abort_unless($subscription && $subscription->status === 'active', 404);

        $service->cancel($subscription, true);

        return back()->with('success', 'Your subscription will not renew. Everything keeps working until '.($subscription->fresh()?->current_period_end?->format('d M Y') ?? 'the end of the period').'.');
    }

    public function invoice(int $invoice): View
    {
        $model = Invoice::query()->where('tenant_id', $this->tenant()->id)->findOrFail($invoice);

        return view('saas::invoice', ['invoice' => $model->load(['tenant', 'plan']), 'back' => route('admin.billing.index')]);
    }

    private function tenant(): Tenant
    {
        return Tenancy::tenant() ?? abort(404);
    }
}
