<?php

declare(strict_types=1);

namespace App\Modules\Saas\Billing;

use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Billing via Checkout (international cards). Talks to the REST API directly, no SDK needed.
 *
 * Setup (dashboard.stripe.com):
 *  1. Product catalogue: one product per plan with a monthly and a yearly price → paste "price_…" ids into SaaS > Plans.
 *  2. Developers > API keys → STRIPE_SECRET in .env
 *  3. Developers > Webhooks → https://CENTRAL_DOMAIN/billing/webhooks/stripe, signing secret → STRIPE_WEBHOOK_SECRET,
 *     events: checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.updated,
 *     customer.subscription.deleted
 */
class StripeGateway implements Gateway
{
    private const API = 'https://api.stripe.com/v1';

    public function name(): string
    {
        return 'stripe';
    }

    public function configured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function checkout(Tenant $tenant, Plan $plan, string $interval, Subscription $subscription, string $returnUrl, string $cancelUrl): string
    {
        $priceId = $plan->gatewayId('stripe', $interval) ?? throw new RuntimeException("Plan {$plan->name} has no Stripe price id for {$interval}ly billing.");

        $session = $this->http()->asForm()->post(self::API.'/checkout/sessions', [
            'mode' => 'subscription',
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'customer_email' => $tenant->email,
            'client_reference_id' => (string) $subscription->id,
            'success_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'subscription_data' => ['metadata' => ['tenant_id' => $tenant->id, 'subscription_ref' => (string) $subscription->id]],
            'automatic_tax' => ['enabled' => 'false'],
        ])->throw()->json();

        return (string) $session['url'];
    }

    public function cancel(Subscription $subscription, bool $atPeriodEnd = true): void
    {
        if (! $subscription->gateway_id) {
            return;
        }

        $atPeriodEnd
            ? $this->http()->asForm()->post(self::API."/subscriptions/{$subscription->gateway_id}", ['cancel_at_period_end' => 'true'])->throw()
            : $this->http()->delete(self::API."/subscriptions/{$subscription->gateway_id}")->throw();
    }

    /**
     * Stripe-Signature: t=1700000000,v1=hex(hmac_sha256("{t}.{body}", secret)). Rejects events older than 5 minutes.
     */
    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $parts = collect(explode(',', (string) $request->header('Stripe-Signature')))
            ->mapWithKeys(fn (string $p) => [trim(strtok($p, '=')) => (string) substr($p, strpos($p, '=') + 1)]);
        $time = (int) ($parts['t'] ?? 0);
        $signature = (string) ($parts['v1'] ?? '');

        return $secret !== '' && $signature !== '' && abs(time() - $time) <= 300
            && hash_equals(hash_hmac('sha256', $time.'.'.$request->getContent(), $secret), $signature);
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $type = (string) $request->input('type');
        $object = (array) $request->input('data.object', []);

        return match ($type) {
            'checkout.session.completed' => ! empty($object['subscription']) ? new WebhookEvent(
                type: 'activated',
                gatewaySubscriptionId: (string) $object['subscription'],
                customerId: $object['customer'] ?? null,
                subscriptionRef: $object['client_reference_id'] ?? null,
            ) : null,
            'invoice.paid' => ! empty($object['subscription']) ? new WebhookEvent(
                type: 'paid',
                gatewaySubscriptionId: (string) $object['subscription'],
                paymentId: (string) ($object['payment_intent'] ?? $object['id']),
                amount: (int) ($object['amount_paid'] ?? 0),
                currency: strtoupper((string) ($object['currency'] ?? '')),
                periodStart: isset($object['lines']['data'][0]['period']['start']) ? Carbon::createFromTimestamp((int) $object['lines']['data'][0]['period']['start']) : null,
                periodEnd: isset($object['lines']['data'][0]['period']['end']) ? Carbon::createFromTimestamp((int) $object['lines']['data'][0]['period']['end']) : null,
                customerId: $object['customer'] ?? null,
                subscriptionRef: $object['subscription_details']['metadata']['subscription_ref'] ?? null,
            ) : null,
            'invoice.payment_failed' => ! empty($object['subscription'])
                ? new WebhookEvent(type: 'payment_failed', gatewaySubscriptionId: (string) $object['subscription']) : null,
            'customer.subscription.updated' => ($object['cancel_at_period_end'] ?? false)
                ? new WebhookEvent(type: 'cancelled', gatewaySubscriptionId: (string) $object['id'],
                    periodEnd: isset($object['current_period_end']) ? Carbon::createFromTimestamp((int) $object['current_period_end']) : null)
                : null,
            'customer.subscription.deleted' => new WebhookEvent(type: 'ended', gatewaySubscriptionId: (string) ($object['id'] ?? '')),
            default => null,
        };
    }

    private function http(): PendingRequest
    {
        return Http::withToken((string) config('services.stripe.secret'))->acceptJson()->timeout(20);
    }
}
