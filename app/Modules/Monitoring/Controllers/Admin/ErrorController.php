<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * System > Monitoring > Error log. Rows are written by App\Core\Monitoring\ErrorRecorder.
 */
class ErrorController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:monitoring.view', only: ['index', 'show']),
            new Middleware('permission:monitoring.manage', except: ['index', 'show']),
        ];
    }

    public function index(Request $request): Response
    {
        return DataTable::of(ErrorLog::query(), $request)
            ->search(['message', 'class', 'url', 'file'])
            ->sortable(['last_seen_at', 'first_seen_at', 'occurrences', 'class'], default: 'last_seen_at')
            ->filter('state', 'Status', ['open' => 'Open', 'resolved' => 'Fixed'],
                fn ($query, string $value) => $value === 'open' ? $query->whereNull('resolved_at') : $query->whereNotNull('resolved_at'))
            ->filter('user_type', 'Where', ['staff' => 'Admin panel', 'customer' => 'Customer', 'api' => 'API', 'console' => 'Console / jobs'])
            ->dateRange('last_seen_at', 'Last seen')
            ->bulk(route('admin.errors.bulk'), ['resolve' => 'monitoring.manage', 'delete' => 'monitoring.manage'])
            ->render('monitoring::admin.errors.index', 'monitoring::admin.errors._table');
    }

    public function show(ErrorLog $error): View
    {
        return view('monitoring::admin.errors.show', ['error' => $error]);
    }

    /**
     * Mark as fixed, or open again. A fixed error that happens again reopens by itself and alerts again.
     */
    public function resolve(ErrorLog $error): RedirectResponse
    {
        $error->forceFill(['resolved_at' => $error->resolved_at ? null : now()])->save();

        return back()->with('success', $error->resolved_at ? 'Marked as fixed. You will be told if it happens again.' : 'Marked as open.');
    }

    public function destroy(ErrorLog $error): RedirectResponse
    {
        $error->delete();

        return redirect()->route('admin.errors.index')->with('success', 'Error deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['resolve', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $query = ErrorLog::query()->whereKey($data['ids']);
        $count = $data['action'] === 'resolve' ? $query->update(['resolved_at' => now()]) : $query->delete();

        return back()->with('success', "{$count} error(s) updated.");
    }

    public function clearResolved(): RedirectResponse
    {
        $count = ErrorLog::query()->whereNotNull('resolved_at')->delete();

        return back()->with('success', "{$count} fixed error(s) deleted.");
    }
}
