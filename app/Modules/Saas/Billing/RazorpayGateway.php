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
 * Razorpay Subscriptions (India: cards, UPI autopay, netbanking).
 *
 * Setup (dashboard.razorpay.com):
 *  1. Subscriptions > Plans: create one plan per price, paste its id ("plan_…") into SaaS > Plans.
 *  2. Settings > API keys → RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET in .env
 *  3. Settings > Webhooks → https://CENTRAL_DOMAIN/billing/webhooks/razorpay, secret → RAZORPAY_WEBHOOK_SECRET,
 *     events: subscription.activated, subscription.charged, subscription.pending, subscription.halted,
 *     subscription.cancelled, subscription.completed
 */
class RazorpayGateway implements Gateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function name(): string
    {
        return 'razorpay';
    }

    public function configured(): bool
    {
        return filled(config('services.razorpay.key')) && filled(config('services.razorpay.secret'));
    }

    public function checkout(Tenant $tenant, Plan $plan, string $interval, Subscription $subscription, string $returnUrl, string $cancelUrl): string
    {
        $planId = $plan->gatewayId('razorpay', $interval) ?? throw new RuntimeException("Plan {$plan->name} has no Razorpay plan id for {$interval}ly billing.");

        $response = $this->http()->post(self::API.'/subscriptions', [
            'plan_id' => $planId,
            'total_count' => $interval === 'year' ? 10 : 120, // renewals before Razorpay stops (max 10 years)
            'customer_notify' => 1,
            'notes' => ['tenant_id' => $tenant->id, 'subscription_ref' => (string) $subscription->id],
        ])->throw()->json();

        $subscription->forceFill(['gateway_id' => $response['id']])->save();

        // Razorpay's hosted page; after paying the customer sees Razorpay's confirmation.
        return (string) $response['short_url'];
    }

    public function cancel(Subscription $subscription, bool $atPeriodEnd = true): void
    {
        if ($subscription->gateway_id) {
            $this->http()->post(self::API."/subscriptions/{$subscription->gateway_id}/cancel", [
                'cancel_at_cycle_end' => $atPeriodEnd ? 1 : 0,
            ])->throw();
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');
        $signature = (string) $request->header('X-Razorpay-Signature');

        return $secret !== '' && $signature !== ''
            && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $event = (string) $request->input('event');
        $sub = (array) $request->input('payload.subscription.entity', []);
        $payment = (array) $request->input('payload.payment.entity', []);

        if (empty($sub['id'])) {
            return null;
        }

        $type = match ($event) {
            'subscription.activated' => 'activated',
            'subscription.charged' => 'paid',
            'subscription.pending', 'subscription.halted' => 'payment_failed',
            'subscription.cancelled' => 'cancelled',
            'subscription.completed' => 'ended',
            default => null,
        };

        if ($type === null) {
            return null;
        }

        return new WebhookEvent(
            type: $type,
            gatewaySubscriptionId: (string) $sub['id'],
            paymentId: $payment['id'] ?? null,
            amount: isset($payment['amount']) ? (int) $payment['amount'] : null,
            currency: isset($payment['currency']) ? strtoupper((string) $payment['currency']) : null,
            periodStart: ! empty($sub['current_start']) ? Carbon::createFromTimestamp((int) $sub['current_start']) : null,
            periodEnd: ! empty($sub['current_end']) ? Carbon::createFromTimestamp((int) $sub['current_end']) : null,
            customerId: $sub['customer_id'] ?? null,
            subscriptionRef: $sub['notes']['subscription_ref'] ?? null,
        );
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth((string) config('services.razorpay.key'), (string) config('services.razorpay.secret'))
            ->acceptJson()->asJson()->timeout(20);
    }
}
