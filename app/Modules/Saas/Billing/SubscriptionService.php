<?php

declare(strict_types=1);

namespace App\Modules\Saas\Billing;

use App\Core\Tenancy\Tenancy;
use App\Modules\Saas\Models\Invoice;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Notifications\TenantBillingNotice;
use App\Notifications\AdminAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Everything that changes a tenant's subscription, in one place:
 * webhooks (Razorpay, Stripe), manual payments recorded by staff, and the nightly check.
 *
 * Tenant status follows the subscription:
 *   trial ──(pays)──▶ active ──(payment fails)──▶ past_due ──(grace days)──▶ suspended
 *     └──(trial ends, no payment, grace days)──▶ suspended          cancelled = runs to period end, then suspended
 */
class SubscriptionService
{
    public function __construct(private readonly GatewayManager $gateways) {}

    /**
     * Tenant chose a plan and a gateway on their Billing page → hosted payment page URL.
     */
    public function startCheckout(Tenant $tenant, Plan $plan, string $interval, string $gateway, string $returnUrl, string $cancelUrl): string
    {
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => $gateway,
            'interval' => $interval,
            'status' => 'pending',
        ]);

        return $this->gateways->get($gateway)->checkout($tenant, $plan, $interval, $subscription, $returnUrl, $cancelUrl);
    }

    /**
     * A verified webhook from a gateway.
     */
    public function handle(string $gateway, WebhookEvent $event): void
    {
        $subscription = Subscription::query()->where('gateway', $gateway)->where('gateway_id', $event->gatewaySubscriptionId)->first()
            ?? ($event->subscriptionRef ? Subscription::query()->where('gateway', $gateway)->find($event->subscriptionRef) : null);

        if (! $subscription) {
            return; // not ours (or created outside the app)
        }

        DB::connection($subscription->getConnectionName())->transaction(function () use ($subscription, $event) {
            $subscription->forceFill(array_filter([
                'gateway_id' => $subscription->gateway_id ?: $event->gatewaySubscriptionId,
                'gateway_customer_id' => $event->customerId ?: $subscription->gateway_customer_id,
            ]))->save();

            match ($event->type) {
                'activated' => $this->activate($subscription, $event->periodStart, $event->periodEnd),
                'paid' => $this->paid($subscription, $event),
                'payment_failed' => $this->failed($subscription),
                'cancelled' => $this->cancelled($subscription, $event->periodEnd),
                'ended' => $this->ended($subscription),
                default => null,
            };
        });
    }

    /**
     * Staff record a payment by hand (bank transfer, cheque, UPI to your account): SaaS > Tenant > Record payment.
     */
    public function recordManualPayment(Tenant $tenant, Plan $plan, string $interval, int $amountBeforeTax, ?string $reference = null): Invoice
    {
        $start = now();
        $end = $interval === 'year' ? now()->addYear() : now()->addMonth();

        // Extend the current manual subscription, or start one.
        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->where('gateway', 'manual')
            ->whereIn('status', ['active', 'past_due'])->latest('id')->first();

        if ($subscription && $subscription->current_period_end?->isFuture()) {
            $start = $subscription->current_period_end;
            $end = $interval === 'year' ? $start->copy()->addYear() : $start->copy()->addMonth();
        }

        $subscription ??= Subscription::query()->create(['tenant_id' => $tenant->id, 'gateway' => 'manual', 'status' => 'active']);
        $subscription->forceFill([
            'plan_id' => $plan->id, 'interval' => $interval, 'status' => 'active',
            'current_period_start' => $subscription->current_period_start ?? $start, 'current_period_end' => $end,
        ])->save();

        $this->switchTenant($tenant, $plan, 'active');

        return $this->createInvoice($tenant, $subscription, $plan, $amountBeforeTax, null, 'manual', $reference, $start, $end, paid: true);
    }

    /**
     * Stop renewing. With a gateway, it keeps access until the paid period ends.
     */
    public function cancel(Subscription $subscription, bool $atPeriodEnd = true): void
    {
        if ($subscription->gateway !== 'manual') {
            $this->gateways->get($subscription->gateway)->cancel($subscription, $atPeriodEnd);
        }

        $atPeriodEnd && $subscription->current_period_end?->isFuture()
            ? $this->cancelled($subscription, $subscription->current_period_end)
            : $this->ended($subscription);
    }

    /**
     * Nightly (saas:check-subscriptions): trials and grace periods that ran out → suspended;
     * cancelled subscriptions past their end → ended. Returns the number of tenants changed.
     */
    public function checkAll(): int
    {
        $grace = (int) config('starter.tenancy.grace_days', 7);
        $changed = 0;

        // Trial over, nothing paid.
        Tenant::query()->where('status', 'trial')->where('trial_ends_at', '<', now()->subDays($grace))->lazyById()
            ->each(function (Tenant $tenant) use (&$changed) {
                $this->suspend($tenant, 'Your free trial has ended.');
                $changed++;
            });

        // Trial just ended (inside the grace period): remind once.
        Tenant::query()->where('status', 'trial')->whereBetween('trial_ends_at', [now()->subDay(), now()])->lazyById()
            ->each(fn (Tenant $tenant) => Notification::route('mail', $tenant->email)->notify(new TenantBillingNotice($tenant, 'trial_ended')));

        // Payment failed and the grace period is over.
        Subscription::query()->where('status', 'past_due')->where('updated_at', '<', now()->subDays($grace))->with('tenant')->lazyById()
            ->each(function (Subscription $s) use (&$changed) {
                $s->forceFill(['status' => 'ended'])->save();
                $this->suspend($s->tenant, 'We could not collect your payment.');
                $changed++;
            });

        // Cancelled subscriptions, and manual ones nobody renewed, whose paid period (+ grace) is over.
        // (Gateway subscriptions renew themselves; their end arrives as a webhook.)
        Subscription::query()
            ->where(fn ($q) => $q->where('status', 'cancelled')->orWhere(fn ($m) => $m->where('gateway', 'manual')->where('status', 'active')))
            ->where('current_period_end', '<', now()->subDays($grace))->with('tenant')->lazyById()
            ->each(function (Subscription $s) use (&$changed) {
                $this->ended($s);
                $changed++;
            });

        return $changed;
    }

    public function suspend(Tenant $tenant, string $reason): void
    {
        if ($tenant->status === 'suspended') {
            return;
        }

        $tenant->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();
        Notification::route('mail', $tenant->email)->notify(new TenantBillingNotice($tenant, 'suspended', $reason));
        AdminAlert::toAdmins("{$tenant->name} was suspended: {$reason}", route('admin.saas.tenants.show', $tenant), 'saas.view', 'bi-pause-circle');
    }

    public function reactivate(Tenant $tenant): void
    {
        $tenant->forceFill(['status' => $tenant->currentSubscription ? 'active' : ($tenant->trial_ends_at?->isFuture() ? 'trial' : 'active'), 'suspended_at' => null])->save();
    }

    private function activate(Subscription $subscription, ?Carbon $start, ?Carbon $end): void
    {
        // Only one live subscription per tenant: end older ones (e.g. after changing plan).
        Subscription::query()->where('tenant_id', $subscription->tenant_id)->whereKeyNot($subscription->id)
            ->whereIn('status', ['active', 'past_due', 'cancelled', 'pending'])->lazyById()
            ->each(function (Subscription $old) {
                if ($old->status !== 'pending' && $old->gateway !== 'manual') {
                    rescue(fn () => $this->gateways->get($old->gateway)->cancel($old, false), report: false);
                }
                $old->forceFill(['status' => 'ended'])->save();
            });

        $subscription->forceFill([
            'status' => 'active',
            'current_period_start' => $start ?? $subscription->current_period_start ?? now(),
            'current_period_end' => $end ?? $subscription->current_period_end,
        ])->save();

        $this->switchTenant($subscription->tenant, $subscription->plan, 'active');
    }

    private function paid(Subscription $subscription, WebhookEvent $event): void
    {
        if ($event->paymentId && Invoice::query()->where('gateway_payment_id', $event->paymentId)->exists()) {
            return; // webhook delivered twice
        }

        if ($subscription->status !== 'active') {
            $this->activate($subscription, $event->periodStart, $event->periodEnd);
        }

        $subscription->forceFill(array_filter([
            'status' => 'active',
            'current_period_start' => $event->periodStart,
            'current_period_end' => $event->periodEnd,
        ]))->save();

        $plan = $subscription->plan;
        $taxRate = (float) Tenancy::central(fn () => setting('billing_tax_rate', 18));
        // The gateway charged a tax-inclusive amount (plan price + tax); split it back for the invoice.
        $total = $event->amount ?? ($plan ? (int) round($plan->price($subscription->interval) * (1 + $taxRate / 100)) : 0);
        $amount = (int) round($total / (1 + $taxRate / 100));

        $this->createInvoice($subscription->tenant, $subscription, $plan, $amount, $total, $subscription->gateway, $event->paymentId,
            $event->periodStart ?? now(), $event->periodEnd, paid: true, currency: $event->currency);

        if ($subscription->tenant->isBlocked() || $subscription->tenant->status !== 'active') {
            $this->switchTenant($subscription->tenant, $plan, 'active');
        }
    }

    private function failed(Subscription $subscription): void
    {
        $subscription->forceFill(['status' => 'past_due'])->save();
        $tenant = $subscription->tenant;
        $tenant->forceFill(['status' => 'past_due'])->save();
        Notification::route('mail', $tenant->email)->notify(new TenantBillingNotice($tenant, 'payment_failed'));
        AdminAlert::toAdmins("Payment failed for {$tenant->name}", route('admin.saas.tenants.show', $tenant), 'saas.view', 'bi-credit-card');
    }

    private function cancelled(Subscription $subscription, ?Carbon $periodEnd): void
    {
        $subscription->forceFill([
            'status' => 'cancelled',
            'cancel_at_period_end' => true,
            'cancelled_at' => now(),
            'current_period_end' => $periodEnd ?? $subscription->current_period_end,
        ])->save();
    }

    private function ended(Subscription $subscription): void
    {
        $subscription->forceFill(['status' => 'ended', 'cancelled_at' => $subscription->cancelled_at ?? now()])->save();

        $tenant = $subscription->tenant;
        $stillPaying = $tenant->subscriptions()->whereKeyNot($subscription->id)->whereIn('status', ['active', 'past_due'])->exists();
        if (! $stillPaying) {
            $this->suspend($tenant, 'Your subscription has ended.');
        }
    }

    private function switchTenant(Tenant $tenant, ?Plan $plan, string $status): void
    {
        $tenant->forceFill([
            'plan_id' => $plan->id ?? $tenant->plan_id,
            'status' => $status,
            'suspended_at' => null,
            'trial_ends_at' => $status === 'active' ? null : $tenant->trial_ends_at,
        ])->save();
    }

    private function createInvoice(
        Tenant $tenant, ?Subscription $subscription, ?Plan $plan, int $amount, ?int $total, string $gateway, ?string $paymentId,
        ?Carbon $start, ?Carbon $end, bool $paid, ?string $currency = null,
    ): Invoice {
        $taxRate = (float) Tenancy::central(fn () => setting('billing_tax_rate', 18));
        $tax = $total !== null ? $total - $amount : (int) round($amount * $taxRate / 100);

        return Invoice::query()->create([
            'number' => $this->nextNumber(),
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription?->id,
            'plan_id' => $plan?->id,
            'description' => ($plan->name ?? 'Subscription').' plan, '.(($subscription->interval ?? 'month') === 'year' ? 'yearly' : 'monthly')
                .($start && $end ? ' ('.$start->format('d M Y').' – '.$end->format('d M Y').')' : ''),
            'amount' => $amount,
            'tax_rate' => $taxRate,
            'tax' => $tax,
            'total' => $total ?? $amount + $tax,
            'currency' => $currency ?: ($plan->currency ?? config('starter.tenancy.currency', 'INR')),
            'status' => $paid ? 'paid' : 'open',
            'gateway' => $gateway,
            'gateway_payment_id' => $paymentId,
            'period_start' => $start,
            'period_end' => $end,
            'paid_at' => $paid ? now() : null,
            'due_at' => $paid ? null : now()->addDays(7),
            'billing' => ['name' => $tenant->name, 'email' => $tenant->email, 'gstin' => $tenant->gstin, 'address' => $tenant->billing_address],
        ]);
    }

    /**
     * INV-2026-00042: prefix from Settings > Billing, a yearly counter.
     */
    private function nextNumber(): string
    {
        $prefix = (string) Tenancy::central(fn () => setting('billing_invoice_prefix', 'INV'));
        $year = now()->year;
        $count = Invoice::query()->where('number', 'like', "{$prefix}-{$year}-%")->count() + 1;

        return sprintf('%s-%d-%05d', $prefix, $year, $count);
    }
}
