<?php

declare(strict_types=1);

namespace App\Modules\Activity\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class ActivityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:activity.view', only: ['index', 'show']),
            new Middleware('permission:activity.delete', only: ['prune']),
        ];
    }

    public function index(Request $request): Response
    {
        $userType = (new User)->getMorphClass();

        return DataTable::of(Activity::query()->with(['causer', 'subject']), $request)
            ->search(['description', 'subject_type'])
            ->sortable(['created_at', 'event', 'log_name'], default: 'created_at')
            ->filter('user', 'User', User::query()->orderBy('name')->pluck('name', 'id')->all(),
                fn ($query, string $id) => $query->where('causer_type', $userType)->where('causer_id', $id))
            ->filter('event', 'Event', ['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted', 'login' => 'Sign in', 'logout' => 'Sign out', 'impersonate' => 'Impersonate'])
            ->filter('log', 'Area', Activity::query()->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')->filter()->all(),
                fn ($query, string $log) => $query->where('log_name', $log))
            ->dateRange('created_at', 'Date')
            ->perPage(25)
            ->export('activity', [
                'When' => 'created_at',
                'Who' => fn (Activity $a) => $a->causer instanceof User ? $a->causer->name : 'System',
                'Event' => 'event',
                'Description' => 'description',
                'Subject' => fn (Activity $a) => $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : '',
                'Area' => 'log_name',
                'Changes' => fn (Activity $a) => json_encode($a->properties),
            ], 'activity.view')
            ->render('activity::admin.index', 'activity::admin._table');
    }

    public function show(Activity $activity): View
    {
        return view('activity::admin.show', ['activity' => $activity->load(['causer', 'subject'])]);
    }

    /**
     * Delete entries older than N days (the scheduler also does this daily).
     */
    public function prune(Request $request): RedirectResponse
    {
        $days = $request->validate(['days' => ['required', 'integer', 'min:7']])['days'];
        $deleted = Activity::query()->where('created_at', '<', now()->subDays($days))->delete();

        return back()->with('success', "{$deleted} old entries deleted.");
    }
}
