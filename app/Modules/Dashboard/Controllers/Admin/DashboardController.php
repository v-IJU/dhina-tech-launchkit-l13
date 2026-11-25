<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers\Admin;

use App\Core\Tenancy\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Models\Role;
use App\Models\User;
use App\Modules\Blog\Models\Post;
use App\Modules\Inbox\Models\ContactMessage;
use App\Modules\Inbox\Models\Subscriber;
use App\Modules\Monitoring\Models\FailedJob;
use App\Modules\Pages\Models\Page;
use App\Modules\Saas\Support\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Everything on the dashboard comes from your own database; nothing is made up.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $range = in_array((int) $request->query('range'), [7, 14, 30], true) ? (int) $request->query('range') : 14;
        $blog = module_enabled('blog');
        $inbox = module_enabled('inbox');

        return view('dashboard::admin.index', [
            'range' => $range,
            'stats' => $this->stats($blog, $inbox),
            'chart' => $this->chart($range, $inbox),
            'roles' => $this->usersByRole(),
            'recentUsers' => User::query()->with('roles')->latest()->limit(5)->get(),
            'latestMessages' => $inbox ? ContactMessage::query()->latest()->limit(4)->get() : collect(),
            'activity' => Activity::query()->with('causer')->latest()->limit(6)->get(),
            'quickActions' => $this->quickActions($request->user()),
            // Tenants see their plan and usage; server status is only for you (central / single business).
            'services' => Tenancy::inTenant() ? [] : $this->systemStatus(),
            'planUsage' => Tenancy::inTenant() ? PlanLimits::usage() : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stats(bool $blog, bool $inbox): array
    {
        $stats = [[
            'label' => 'Total users', 'value' => User::query()->count(), 'icon' => 'bi-people', 'color' => '#2563eb',
            'note' => User::query()->where('status', 'active')->count().' active', 'tone' => 'success',
        ]];

        if ($blog) {
            $published = Post::query()->published()->count();
            $stats[] = ['label' => 'Published posts', 'value' => $published, 'icon' => 'bi-journal-text', 'color' => '#16a34a',
                'note' => (Post::query()->count() - $published).' not live', 'tone' => 'warning'];
        }
        if ($inbox) {
            $stats[] = ['label' => 'Unread enquiries', 'value' => ContactMessage::query()->unread()->count(), 'icon' => 'bi-inbox', 'color' => '#ea580c',
                'note' => ContactMessage::query()->count().' total', 'tone' => 'secondary'];
            $stats[] = ['label' => 'Newsletter subscribers', 'value' => Subscriber::query()->count(), 'icon' => 'bi-envelope-paper', 'color' => '#7c3aed',
                'note' => Subscriber::query()->where('created_at', '>=', now()->subDays(30))->count().' in 30 days', 'tone' => 'info'];
        }
        if (module_enabled('pages') && count($stats) < 4) {
            $stats[] = ['label' => 'Website pages', 'value' => Page::query()->count(), 'icon' => 'bi-window', 'color' => '#0891b2',
                'note' => Page::query()->where('status', 'published')->count().' published', 'tone' => 'success'];
        }

        return $stats;
    }

    /**
     * Daily enquiries and new users for the selected range.
     *
     * @return array<string, mixed>
     */
    private function chart(int $days, bool $inbox): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $labels = [];
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $labels[] = $day->format('j M');
            $dates[] = $day->toDateString();
        }

        $perDay = function (string $table) use ($start, $dates): array {
            $counts = DB::table($table)->where('created_at', '>=', $start)->pluck('created_at')
                ->countBy(fn ($d) => Carbon::parse($d)->toDateString());

            return array_map(fn ($d) => (int) ($counts[$d] ?? 0), $dates);
        };

        $series = [];
        if ($inbox) {
            $series[] = ['label' => 'Contact enquiries', 'color' => '#2563eb', 'values' => $perDay('contact_messages')];
            $series[] = ['label' => 'Newsletter signups', 'color' => '#f59e0b', 'values' => $perDay('newsletter_subscribers')];
        }
        $series[] = ['label' => 'New users', 'color' => '#16a34a', 'values' => $perDay('users')];

        return [
            'labels' => $labels,
            'series' => $series,
            'total' => array_sum($series[0]['values']),
            'title' => $series[0]['label'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usersByRole(): array
    {
        $roles = Role::query()->withCount('users')->orderBy('id')->get();

        return [
            'labels' => $roles->map(fn ($r) => $r->display_name ?: $r->name)->all(),
            'values' => $roles->pluck('users_count')->all(),
            'colors' => $roles->map(fn ($r) => $r->color ?: '#475569')->all(),
            'total' => User::query()->count(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function quickActions(User $user): array
    {
        $actions = [
            ['Add user', 'bi-person-plus', 'admin.users.create', 'users.create', 'primary', 'users'],
            ['Write post', 'bi-pencil-square', 'admin.blog.posts.create', 'blog.create', 'success', 'blog'],
            ['Create role', 'bi-shield-plus', 'admin.roles.create', 'roles.create', 'warning', 'roles'],
            ['Upload media', 'bi-cloud-upload', 'admin.media.index', 'media.create', 'info', 'media'],
            ['Edit pages', 'bi-window', 'admin.pages.index', 'pages.edit', 'primary', 'pages'],
            ['New module', 'bi-box-seam', 'admin.modules.index', 'modules.create', 'danger', 'modules'],
        ];

        // module_enabled: in SaaS mode only what this tenant's plan includes.
        return collect($actions)
            ->filter(fn ($a) => module_enabled($a[5]) && \Route::has($a[2]) && $user->can($a[3]))
            ->map(fn ($a) => ['label' => $a[0], 'icon' => $a[1], 'url' => route($a[2]), 'tone' => $a[4]])
            ->values()->all();
    }

    /**
     * @return array<int, array<string, string|bool>>
     */
    private function systemStatus(): array
    {
        $status = [];

        $t = microtime(true);
        try {
            DB::select('select 1');
            $status[] = ['name' => 'Database', 'detail' => config('database.default'), 'value' => round((microtime(true) - $t) * 1000).' ms', 'ok' => true];
        } catch (Throwable) {
            $status[] = ['name' => 'Database', 'detail' => 'Not reachable', 'value' => 'down', 'ok' => false];
        }

        $status[] = $this->horizonStatus();

        $lastRun = Cache::get('scheduler:last-run');
        $status[] = [
            'name' => 'Scheduler', 'detail' => 'php artisan schedule:work',
            'value' => $lastRun ? 'ran '.Carbon::parse($lastRun)->diffForHumans() : 'not seen yet',
            'ok' => $lastRun && Carbon::parse($lastRun)->gt(now()->subMinutes(5)),
        ];

        $status[] = [
            'name' => 'Broadcasting', 'detail' => 'Live notifications',
            'value' => config('broadcasting.default'), 'ok' => config('broadcasting.default') === 'reverb',
        ];

        $status[] = $this->lastBackup();

        if (module_enabled('monitoring')) {
            $errors = ErrorLog::query()->open()->where('last_seen_at', '>=', now()->subDay())->count();
            $status[] = ['name' => 'Errors', 'detail' => 'Open, last 24 hours', 'value' => (string) $errors, 'ok' => $errors === 0,
                'url' => auth()->user()?->can('monitoring.view') ? route('admin.errors.index', ['state' => 'open']) : null];
            $failed = FailedJob::query()->count();
            $status[] = ['name' => 'Failed jobs', 'detail' => 'Waiting for retry', 'value' => (string) $failed, 'ok' => $failed === 0,
                'url' => auth()->user()?->can('monitoring.view') ? route('admin.failed-jobs.index') : null];
        }

        return $status;
    }

    /**
     * @return array<string, string|bool>
     */
    private function horizonStatus(): array
    {
        if (config('queue.default') !== 'redis') {
            return ['name' => 'Queue', 'detail' => 'Driver: '.config('queue.default'), 'value' => config('queue.default'), 'ok' => config('queue.default') === 'sync'];
        }

        try {
            $masters = app(MasterSupervisorRepository::class)->all();
            $running = collect($masters)->contains(fn ($m) => ($m->status ?? '') === 'running');

            return ['name' => 'Queue (Horizon)', 'detail' => 'Redis', 'value' => $running ? 'running' : 'stopped', 'ok' => $running];
        } catch (Throwable) {
            return ['name' => 'Queue (Horizon)', 'detail' => 'Redis not reachable', 'value' => 'unknown', 'ok' => false];
        }
    }

    /**
     * @return array<string, string|bool>
     */
    private function lastBackup(): array
    {
        try {
            $disk = config('backup.backup.destination.disks')[0] ?? 'local';
            $files = collect(Storage::disk($disk)->allFiles(config('backup.backup.name')))
                ->filter(fn ($f) => str_ends_with($f, '.zip'))
                ->map(fn ($f) => Storage::disk($disk)->lastModified($f))->sortDesc();
            $last = $files->first();

            return ['name' => 'Last backup', 'detail' => 'spatie/laravel-backup', 'value' => $last ? Carbon::createFromTimestamp($last)->diffForHumans() : 'none yet',
                'ok' => $last && $last > now()->subDays(2)->timestamp];
        } catch (Throwable) {
            return ['name' => 'Last backup', 'detail' => 'Backup disk not reachable', 'value' => 'unknown', 'ok' => false];
        }
    }
}
