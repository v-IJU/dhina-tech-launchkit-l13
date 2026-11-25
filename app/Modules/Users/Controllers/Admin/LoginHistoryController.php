<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users > Sign-in history: every sign-in, failed attempt, sign-out and lockout.
 */
class LoginHistoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:users.view')];
    }

    public function __invoke(Request $request): Response
    {
        return DataTable::of(LoginEvent::query()->staff()->with('user'), $request)
            ->search(['email', 'ip_address', 'browser', 'platform'])
            ->sortable(['created_at', 'event', 'email', 'ip_address'], default: 'created_at')
            ->filter('event', 'Event', LoginEvent::EVENTS)
            ->filter('new_device', 'Device', ['1' => 'New device only'], fn ($query) => $query->where('is_new_device', true))
            ->dateRange('created_at', 'Date')
            ->perPage(25)
            ->export('sign-in-history', [
                'When' => 'created_at',
                'Event' => fn (LoginEvent $e) => LoginEvent::EVENTS[$e->event] ?? $e->event,
                'Email' => 'email',
                'User' => 'user.name',
                'IP address' => 'ip_address',
                'Device' => fn (LoginEvent $e) => $e->device(),
                'New device' => 'is_new_device',
            ], 'users.export')
            ->render('users::admin.logins.index', 'users::admin.logins._table');
    }
}
