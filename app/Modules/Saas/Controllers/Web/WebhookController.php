<?php

declare(strict_types=1);

namespace App\Modules\Saas\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Saas\Billing\GatewayManager;
use App\Modules\Saas\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /billing/webhooks/{razorpay|stripe}: the provider tells us about payments.
 * Rejected (400) unless the signature matches the webhook secret in .env.
 * Always answers 200 for valid events, so the provider doesn't keep retrying.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, GatewayManager $gateways, SubscriptionService $service): JsonResponse
    {
        $driver = $gateways->get($gateway);

        if (! $driver->verifyWebhook($request)) {
            Log::channel('stack')->warning("Rejected {$gateway} webhook: bad signature", ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if ($event = $driver->parseWebhook($request)) {
            $service->handle($gateway, $event);
        }

        return response()->json(['received' => true]);
    }
}
