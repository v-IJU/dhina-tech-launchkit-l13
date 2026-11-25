<?php

declare(strict_types=1);

namespace App\Modules\Saas\Billing;

use Illuminate\Support\Carbon;

/**
 * What a payment webhook means, independent of the provider.
 *
 *  type: activated | paid | payment_failed | cancelled | ended
 */
final class WebhookEvent
{
    public function __construct(
        public readonly string $type,
        public readonly string $gatewaySubscriptionId,
        public readonly ?string $paymentId = null,
        public readonly ?int $amount = null,               // minor units, as charged (incl. tax)
        public readonly ?string $currency = null,
        public readonly ?Carbon $periodStart = null,
        public readonly ?Carbon $periodEnd = null,
        public readonly ?string $customerId = null,
        public readonly ?string $subscriptionRef = null,   // our subscription id sent at checkout
    ) {}
}
