<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customers > Sign-in history: website and app sign-ins of every customer.
 * (Staff sign-ins are under Users > Sign-in history.)
 */
class CustomerLoginController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:customers.view')];
    }

    public function __invoke(Request $request): Response
    {
        return DataTable::of(LoginEvent::query()->customers()->with('customer'), $request)
            ->search(['email', 'ip_address', 'browser', 'platform'])
            ->sortable(['created_at', 'event', 'email', 'ip_address'], default: 'created_at')
            ->filter('event', 'Event', array_diff_key(LoginEvent::EVENTS, ['lockout' => true]))
            ->filter('new_device', 'Device', ['1' => 'New device only'], fn ($query) => $query->where('is_new_device', true))
            ->dateRange('created_at', 'Date')
            ->perPage(25)
            ->export('customer-sign-ins', [
                'When' => 'created_at',
                'Event' => fn (LoginEvent $e) => LoginEvent::EVENTS[$e->event] ?? $e->event,
                'Email' => 'email',
                'Customer' => 'customer.name',
                'IP address' => 'ip_address',
                'Device' => fn (LoginEvent $e) => $e->device(),
                'New device' => 'is_new_device',
            ], 'customers.export')
            ->render('customers::admin.logins.index', 'customers::admin.logins._table');
    }
}
