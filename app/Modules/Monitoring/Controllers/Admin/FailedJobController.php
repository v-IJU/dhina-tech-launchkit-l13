<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Models\FailedJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * System > Monitoring > Failed jobs: queue jobs that failed after every try
 * (emails that could not be sent, backups that crashed…). Retry puts them back on the queue.
 * Same as "php artisan queue:retry" / "queue:forget", without a terminal.
 */
class FailedJobController extends Controller implements HasMiddleware
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
        $queues = FailedJob::query()->distinct()->orderBy('queue')->pluck('queue')->all();

        return DataTable::of(FailedJob::query(), $request)
            ->search(['payload', 'exception', 'uuid'])
            ->sortable(['failed_at', 'queue'], default: 'failed_at')
            ->filter('queue', 'Queue', array_combine($queues, $queues) ?: [])
            ->dateRange('failed_at', 'Failed')
            ->bulk(route('admin.failed-jobs.bulk'), ['retry' => 'monitoring.manage', 'delete' => 'monitoring.manage'])
            ->render('monitoring::admin.jobs.index', 'monitoring::admin.jobs._table');
    }

    public function show(FailedJob $job): View
    {
        return view('monitoring::admin.jobs.show', ['job' => $job]);
    }

    public function retry(FailedJob $job): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$job->uuid]]);

        return redirect()->route('admin.failed-jobs.index')->with('success', $job->jobName().' is back on the queue.');
    }

    public function destroy(FailedJob $job): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $job->uuid]);

        return redirect()->route('admin.failed-jobs.index')->with('success', 'Failed job deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['retry', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $uuids = FailedJob::query()->whereKey($data['ids'])->pluck('uuid')->all();

        if ($data['action'] === 'retry') {
            Artisan::call('queue:retry', ['id' => $uuids]);
        } else {
            FailedJob::query()->whereIn('uuid', $uuids)->delete();
        }

        return back()->with('success', count($uuids).' job(s) '.($data['action'] === 'retry' ? 'sent back to the queue.' : 'deleted.'));
    }

    public function retryAll(): RedirectResponse
    {
        $count = FailedJob::query()->count();
        Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with('success', "{$count} job(s) sent back to the queue.");
    }

    public function flush(): RedirectResponse
    {
        $count = FailedJob::query()->count();
        Artisan::call('queue:flush');

        return back()->with('success', "{$count} failed job(s) deleted.");
    }
}
