<?php

declare(strict_types=1);

namespace App\Modules\Saas\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Saas\Models\Invoice;
use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Support\Money;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * SaaS > Overview: how the business is doing (MRR, subscribers, trials, problems).
 */
class OverviewController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:saas.view')];
    }

    public function __invoke(): View
    {
        $currency = config('starter.tenancy.currency', 'INR');
        $live = Subscription::query()->whereIn('status', ['active', 'past_due'])->with('plan')->get();
        $mrr = $live->sum(fn (Subscription $s) => $s->plan?->monthlyValue($s->interval) ?? 0);
        $byStatus = Tenant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $revenue = collect(range(5, 0))->map(function (int $monthsAgo) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);

            return [
                'label' => $month->format('M'),
                'total' => (int) Invoice::query()->paid()->whereBetween('paid_at', [$month, $month->copy()->endOfMonth()])->sum('total'),
            ];
        });

        return view('saas::admin.overview', [
            'stats' => [
                ['label' => 'Monthly recurring revenue', 'value' => Money::format($mrr, $currency), 'icon' => 'bi-graph-up-arrow', 'hint' => $live->count().' paying'],
                ['label' => 'Revenue this month', 'value' => Money::format((int) Invoice::query()->paid()->where('paid_at', '>=', now()->startOfMonth())->sum('total'), $currency), 'icon' => 'bi-cash-stack', 'hint' => 'Paid invoices'],
                ['label' => 'Tenants', 'value' => number_format((int) $byStatus->sum()), 'icon' => 'bi-buildings', 'hint' => ($byStatus['active'] ?? 0).' active · '.($byStatus['trial'] ?? 0).' trial'],
                ['label' => 'Need attention', 'value' => (string) (($byStatus['past_due'] ?? 0) + ($byStatus['suspended'] ?? 0)), 'icon' => 'bi-exclamation-triangle', 'hint' => ($byStatus['past_due'] ?? 0).' payment due · '.($byStatus['suspended'] ?? 0).' suspended'],
            ],
            'byStatus' => $byStatus,
            'revenue' => $revenue,
            'currency' => $currency,
            'trialsEnding' => Tenant::query()->where('status', 'trial')->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->with('plan')->orderBy('trial_ends_at')->limit(8)->get(),
            'recent' => Tenant::query()->with(['plan', 'domains'])->latest()->limit(8)->get(),
            'problems' => Tenant::query()->whereIn('status', ['past_due', 'suspended'])->with('plan')->latest('updated_at')->limit(8)->get(),
        ]);
    }
}
