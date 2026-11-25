<?php

declare(strict_types=1);

namespace App\Modules\Backups\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Backups\Jobs\RunBackup;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller implements HasMiddleware
{
    /** Maintenance buttons: label => artisan command. Only these can be run. */
    public const TOOLS = [
        'cache' => ['Clear application cache', 'optimize:clear'],
        'optimize' => ['Rebuild route, config & view cache', 'optimize'],
        'sync' => ['Sync module permissions & menus', 'module:sync'],
        'sitemap' => ['Regenerate sitemap.xml', 'sitemap:generate'],
        'queue' => ['Restart queue workers', 'queue:restart'],
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:backups.view', only: ['index']),
            new Middleware('permission:backups.create', only: ['store', 'tool']),
            new Middleware('permission:backups.download', only: ['download']),
            new Middleware('permission:backups.delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $backups = collect();
        $diskError = null;

        try {
            $disk = $this->disk();
            $backups = collect($disk->allFiles(config('backup.backup.name')))
                ->filter(fn ($f) => str_ends_with($f, '.zip'))
                ->map(fn ($f) => [
                    'path' => $f,
                    'name' => basename($f),
                    'size' => Number::fileSize($disk->size($f)),
                    'date' => Carbon::createFromTimestamp($disk->lastModified($f)),
                ])
                ->sortByDesc('date')->values();
        } catch (Throwable $e) {
            $diskError = $e->getMessage();
        }

        return view('backups::admin.index', [
            'backups' => $backups,
            'diskError' => $diskError,
            'tools' => self::TOOLS,
            'system' => $this->systemInfo(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        RunBackup::dispatch($request->input('type') === 'db');

        return back()->with('success', 'Backup started on the queue. You will get a notification when it finishes.');
    }

    public function download(Request $request): StreamedResponse
    {
        $path = $this->validPath($request);

        return $this->disk()->download($path);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->disk()->delete($this->validPath($request));

        return back()->with('success', 'Backup deleted.');
    }

    public function tool(string $tool): RedirectResponse
    {
        [$label, $command] = self::TOOLS[$tool] ?? abort(404);

        try {
            Artisan::call($command);
        } catch (Throwable $e) {
            return back()->with('error', "{$label} failed: ".$e->getMessage());
        }

        activity('system')->event('updated')->log("Ran {$command}");

        return back()->with('success', "{$label} — done.");
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
    }

    /**
     * Only files inside the backup folder can be downloaded or deleted.
     */
    private function validPath(Request $request): string
    {
        $path = (string) $request->input('path');
        $folder = config('backup.backup.name');

        abort_unless(str_starts_with($path, $folder.'/') && ! str_contains($path, '..') && str_ends_with($path, '.zip'), 404);
        abort_unless($this->disk()->exists($path), 404);

        return $path;
    }

    /**
     * @return array<string, string>
     */
    private function systemInfo(): array
    {
        $dbVersion = rescue(fn () => DB::scalar(config('database.default') === 'sqlite' ? 'select sqlite_version()' : 'select version()'), 'unknown', false);

        return [
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Database' => config('database.default').' '.$dbVersion,
            'Cache' => config('cache.default'),
            'Queue' => config('queue.default'),
            'Broadcasting' => config('broadcasting.default'),
            'Mail' => config('mail.default'),
            'Environment' => app()->environment(),
            'Debug mode' => config('app.debug') ? 'ON (turn off in production)' : 'off',
            'Timezone' => config('app.timezone'),
            'Server' => php_uname('s').' '.php_uname('r'),
            'Disk free' => Number::fileSize((float) @disk_free_space(base_path())),
        ];
    }
}
