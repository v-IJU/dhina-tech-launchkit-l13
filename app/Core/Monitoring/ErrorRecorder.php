<?php

declare(strict_types=1);

namespace App\Core\Monitoring;

use App\Core\Tenancy\Tenancy;
use App\Models\ErrorLog;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Notifications\AdminAlert;
use App\Notifications\ErrorOccurred;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Saves every reported exception to the error_logs table (Admin > System > Error log)
 * and alerts staff the first time an error appears, or when a fixed one comes back.
 *
 * Hooked up in bootstrap/app.php: $exceptions->report(fn (Throwable $e) => ErrorRecorder::record($e));
 * Laravel only "reports" real errors: 404s, validation errors and sign-in failures never get here.
 *
 * It must never break the request, so everything is wrapped in try/catch: if the database
 * itself is down, the error still goes to the log file as usual.
 */
final class ErrorRecorder
{
    private static bool $recording = false;

    public static function record(Throwable $e): void
    {
        if (self::$recording) {
            return; // an error while saving an error
        }
        self::$recording = true;

        try {
            if (! Tenancy::central(fn () => setting('error_log_enabled', true))) {
                return;
            }

            [$file, $line] = self::origin($e);
            $tenantId = Tenancy::tenant()?->getTenantKey();
            // Same error in two tenants = two rows, so you can see who is affected.
            $fingerprint = hash('sha256', get_class($e).'|'.$file.'|'.$line.($tenantId ? '|'.$tenantId : ''));
            $context = self::context();

            $log = ErrorLog::query()->where('fingerprint', $fingerprint)->first();
            $isNew = $log === null;
            $reopened = $log?->resolved_at !== null;

            if ($log) {
                $log->forceFill([
                    'message' => Str::limit($e->getMessage(), 2000),
                    'occurrences' => $log->occurrences + 1,
                    'last_seen_at' => now(),
                    'resolved_at' => null,
                ] + $context)->save();
            } else {
                $log = ErrorLog::query()->create([
                    'fingerprint' => $fingerprint,
                    'tenant_id' => $tenantId,
                    'class' => get_class($e),
                    'message' => Str::limit($e->getMessage(), 2000),
                    'file' => $file,
                    'line' => $line,
                    'trace' => self::trace($e),
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                ] + $context);
            }

            if ($isNew || $reopened) {
                // Alerts go to YOUR staff (central database), never to the tenant's users.
                Tenancy::central(fn () => self::alert($log, $reopened));
            }
        } catch (Throwable) {
            // Never let error tracking cause a second error.
        } finally {
            self::$recording = false;
        }
    }

    /**
     * Bell notification + optional email, at most once an hour per error.
     */
    private static function alert(ErrorLog $log, bool $reopened): void
    {
        if (! Cache::add('error-alert:'.$log->fingerprint, true, now()->addHour())) {
            return;
        }

        $title = ($reopened ? 'Error is back: ' : 'New error: ').$log->shortClass().': '.Str::limit($log->message, 80);
        // Always the central/your own panel (APP_URL), even when the error happened on a tenant's domain.
        $url = Route::has('admin.errors.show') ? rtrim((string) config('app.url'), '/').route('admin.errors.show', $log, false) : null;

        if (setting('error_notify_admins', true)) {
            AdminAlert::toAdmins($title, $url, 'monitoring.view', 'bi-bug');
        }

        if ($email = setting('error_alert_email')) {
            Notification::route('mail', $email)->notify(new ErrorOccurred($log, $reopened, $url));
        }
    }

    /**
     * Who was doing what when it happened.
     *
     * @return array<string, mixed>
     */
    private static function context(): array
    {
        $request = request();

        // Artisan commands and queue workers (Horizon) have no route.
        if (app()->runningInConsole() && $request->route() === null) {
            return ['url' => 'artisan '.implode(' ', array_slice($_SERVER['argv'] ?? [], 1)), 'method' => 'CLI', 'user_type' => 'console', 'user_id' => null, 'ip_address' => null];
        }

        $user = $request->user();
        $customer = auth('customer')->user();

        return [
            'url' => Str::limit($request->fullUrl(), 2000),
            'method' => $request->method(),
            'user_type' => match (true) {
                $user instanceof User => $request->is('api/*') ? 'api' : 'staff',
                $user instanceof Customer, $customer !== null => 'customer',
                default => null,
            },
            'user_id' => $user?->getAuthIdentifier() ?? $customer?->getAuthIdentifier(),
            'ip_address' => $request->ip(),
        ];
    }

    /**
     * The first 40 stack frames as text, paths relative to the project.
     */
    private static function trace(Throwable $e): string
    {
        $lines = [];
        foreach (array_slice($e->getTrace(), 0, 40) as $i => $frame) {
            $where = isset($frame['file']) ? self::relative($frame['file']).':'.($frame['line'] ?? '?') : '[internal]';
            $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '').'()';
            $lines[] = "#{$i} {$where}  {$call}";
        }

        $previous = $e->getPrevious();

        return implode("\n", $lines).($previous ? "\n\nCaused by ".get_class($previous).': '.$previous->getMessage() : '');
    }

    /**
     * The first file:line in YOUR code (not vendor/). Every database error is thrown from the same
     * line inside Laravel, so grouping by that would lump them all together; the line in
     * app/ that ran the query is what tells them apart, and what you need to open.
     *
     * @return array{0: string, 1: int}
     */
    private static function origin(Throwable $e): array
    {
        $vendor = DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR;
        $frames = [['file' => $e->getFile(), 'line' => $e->getLine()], ...$e->getTrace()];

        foreach ($frames as $frame) {
            if (isset($frame['file']) && ! str_contains($frame['file'], $vendor)) {
                return [self::relative($frame['file']), (int) ($frame['line'] ?? 0)];
            }
        }

        return [self::relative($e->getFile()), $e->getLine()];
    }

    private static function relative(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
