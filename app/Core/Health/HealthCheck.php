<?php

declare(strict_types=1);

namespace App\Core\Health;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * Is the app able to serve requests?
 *
 *  - /up (load balancer, Docker, deploy.sh) fails only when a CRITICAL check fails:
 *    database, cache, storage. A stopped queue worker must not take the website offline.
 *  - "php artisan health:check" shows every check, including queue workers, scheduler and disk space.
 */
final class HealthCheck
{
    /**
     * @return list<array{name: string, ok: bool, critical: bool, detail: string}>
     */
    public static function run(bool $criticalOnly = false): array
    {
        $checks = [
            self::check('Database', true, function () {
                $t = microtime(true);
                DB::select('select 1');

                return config('database.default').', '.round((microtime(true) - $t) * 1000).' ms';
            }),
            self::check('Cache', true, function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return $ok ? config('cache.default') : throw new \RuntimeException('Could not read back a cached value');
            }),
            self::check('Storage', true, function () {
                foreach ([storage_path('framework/cache'), storage_path('logs'), storage_path('app/public')] as $dir) {
                    if (! is_dir($dir) || ! is_writable($dir)) {
                        throw new \RuntimeException("Not writable: {$dir}");
                    }
                }

                return 'writable';
            }),
        ];

        if ($criticalOnly) {
            return $checks;
        }

        if (in_array('redis', [config('queue.default'), config('cache.default'), config('session.driver')], true)) {
            $checks[] = self::check('Redis', false, fn () => Redis::connection()->ping() ? 'reachable' : throw new \RuntimeException('No reply'));
        }

        if (config('queue.default') === 'redis' && interface_exists(MasterSupervisorRepository::class)) {
            $checks[] = self::check('Queue workers (Horizon)', false, function () {
                $running = collect(app(MasterSupervisorRepository::class)->all())->contains(fn ($m) => ($m->status ?? '') === 'running');

                return $running ? 'running' : throw new \RuntimeException('Horizon is not running');
            });
        }

        $checks[] = self::check('Scheduler', false, function () {
            $last = Cache::get('scheduler:last-run');
            if (! $last || Carbon::parse($last)->lt(now()->subMinutes(5))) {
                throw new \RuntimeException($last ? 'Last ran '.Carbon::parse($last)->diffForHumans() : 'Never ran');
            }

            return 'ran '.Carbon::parse($last)->diffForHumans();
        });

        $checks[] = self::check('Disk space', false, function () {
            $free = @disk_free_space(storage_path());
            $total = @disk_total_space(storage_path());
            if (! $free || ! $total) {
                return 'unknown';
            }
            $percent = (int) round($free / $total * 100);

            return $percent < 10
                ? throw new \RuntimeException("Only {$percent}% free")
                : round($free / 1_073_741_824, 1)." GB free ({$percent}%)";
        });

        return $checks;
    }

    /**
     * @return list<string> names of the failing critical checks
     */
    public static function criticalFailures(): array
    {
        return collect(self::run(criticalOnly: true))->reject(fn (array $c) => $c['ok'])->pluck('name')->values()->all();
    }

    /**
     * @param  callable(): string  $test
     * @return array{name: string, ok: bool, critical: bool, detail: string}
     */
    private static function check(string $name, bool $critical, callable $test): array
    {
        try {
            return ['name' => $name, 'ok' => true, 'critical' => $critical, 'detail' => $test()];
        } catch (Throwable $e) {
            return ['name' => $name, 'ok' => false, 'critical' => $critical, 'detail' => Str::limit($e->getMessage(), 150)];
        }
    }
}
