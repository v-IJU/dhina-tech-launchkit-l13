<?php

declare(strict_types=1);

namespace App\Modules\Saas\Billing;

use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Models\Tenant;
use Illuminate\Http\Request;

/**
 * A payment provider. Razorpay and Stripe implement it; add PayPal, Cashfree… the same way
 * and register it in GatewayManager.
 */
interface Gateway
{
    public function name(): string;

    /** Keys present in .env? */
    public function configured(): bool;

    /**
     * Start a subscription and return the URL to send the tenant to (hosted payment page).
     */
    public function checkout(Tenant $tenant, Plan $plan, string $interval, Subscription $subscription, string $returnUrl, string $cancelUrl): string;

    /**
     * Stop renewing. $atPeriodEnd: keep access until the paid period ends.
     */
    public function cancel(Subscription $subscription, bool $atPeriodEnd = true): void;

    /**
     * Check the webhook really comes from the provider.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Turn the provider's webhook into a common event the SubscriptionService understands.
     */
    public function parseWebhook(Request $request): ?WebhookEvent;
}
