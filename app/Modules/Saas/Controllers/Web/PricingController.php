<?php

declare(strict_types=1);

namespace App\Modules\Saas\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Saas\Models\Plan;
use Illuminate\View\View;

/**
 * /pricing on the central domain: every public, active plan.
 */
class PricingController extends Controller
{
    public function __invoke(): View
    {
        return view('saas::web.pricing', [
            'plans' => Plan::query()->available()->where('is_public', true)->get(),
            'signup' => (bool) setting('saas_signup', true),
        ]);
    }
}
