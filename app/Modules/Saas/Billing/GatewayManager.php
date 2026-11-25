<?php

declare(strict_types=1);

namespace App\Modules\Saas\Billing;

use App\Core\Tenancy\Tenancy;
use App\Modules\Saas\Models\Subscription;
use InvalidArgumentException;

/**
 * Which gateways exist and which ones tenants can pay with right now
 * (keys in .env AND switched on in Settings > Billing).
 */
class GatewayManager
{
    /** @var array<string, class-string<Gateway>> */
    private const GATEWAYS = [
        'razorpay' => RazorpayGateway::class,
        'stripe' => StripeGateway::class,
    ];

    public function get(string $name): Gateway
    {
        $class = self::GATEWAYS[$name] ?? throw new InvalidArgumentException("Unknown payment gateway [{$name}].");

        return app($class);
    }

    /**
     * @return array<string, string> name => label of the gateways a tenant can pay with
     */
    public function available(): array
    {
        $enabled = Tenancy::central(fn () => ['razorpay' => setting('billing_razorpay', true), 'stripe' => setting('billing_stripe', true)]);

        return collect(self::GATEWAYS)->keys()
            ->filter(fn (string $name) => $enabled[$name] ?? false)
            ->mapWithKeys(fn (string $name) => [$name => $this->get($name)])
            ->filter(fn (Gateway $g) => $g->configured())
            ->map(fn (Gateway $g, string $name) => Subscription::GATEWAYS[$name] ?? ucfirst($name))
            ->all();
    }
}
