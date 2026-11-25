<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Settings\SettingsRepository;
use App\Models\ErrorLog;
use App\Notifications\AdminAlert;
use App\Notifications\ErrorOccurred;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Error log, failed jobs, /up and health:check.
 */
class MonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->get('/_boom', fn () => throw new RuntimeException('Boom from a test'));
    }

    public function test_errors_are_recorded_once_counted_and_alerted(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        app(SettingsRepository::class)->set(['error_alert_email' => 'dev@example.com']);

        $this->get('/_boom')->assertStatus(500);
        $this->get('/_boom')->assertStatus(500);

        $log = ErrorLog::query()->sole();
        $this->assertSame(RuntimeException::class, $log->class);
        $this->assertSame('Boom from a test', $log->message);
        $this->assertSame(2, $log->occurrences);
        $this->assertStringEndsWith('/_boom', (string) $log->url);
        $this->assertStringContainsString('tests/Feature/MonitoringTest.php', (string) $log->file);

        // One alert, not one per occurrence.
        Notification::assertSentToTimes($admin, AdminAlert::class, 1);
        Notification::assertSentOnDemand(ErrorOccurred::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'dev@example.com');

        // A fixed error that comes back reopens and alerts again.
        $log->forceFill(['resolved_at' => now()])->save();
        Cache::flush();
        $this->get('/_boom');
        $this->assertNull($log->fresh()->resolved_at);
        Notification::assertSentToTimes($admin, AdminAlert::class, 2);
    }

    public function test_errors_from_inside_laravel_are_grouped_by_the_line_in_your_code(): void
    {
        Route::middleware('web')->get('/_db1', fn () => DB::select('select * from missing_table_one'));
        Route::middleware('web')->get('/_db2', fn () => DB::select('select * from missing_table_two'));

        $this->get('/_db1')->assertStatus(500);
        $this->get('/_db2')->assertStatus(500);

        // Both QueryExceptions are thrown inside vendor/; they come from two different lines here.
        $logs = ErrorLog::query()->get();
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn (ErrorLog $l) => str_starts_with((string) $l->file, 'tests/Feature/MonitoringTest.php')));
    }

    public function test_404s_and_validation_errors_are_not_errors_and_recording_can_be_off(): void
    {
        $this->get('/no-such-page-here')->assertNotFound();
        $this->post(route('customer.login.store'), [])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('error_logs', 0);

        app(SettingsRepository::class)->set(['error_log_enabled' => false]);
        $this->get('/_boom')->assertStatus(500);
        $this->assertDatabaseCount('error_logs', 0);
    }

    public function test_error_log_pages_and_actions(): void
    {
        $this->get('/_boom');
        $log = ErrorLog::query()->sole();
        $this->actingAs($this->superAdmin(), 'web');

        $this->get(route('admin.errors.index'))->assertOk()->assertSee('RuntimeException')->assertSee('Boom from a test');
        $this->get(route('admin.errors.show', $log))->assertOk()->assertSee('Stack trace')->assertSee('MonitoringTest.php');

        $this->post(route('admin.errors.resolve', $log))->assertRedirect();
        $this->assertNotNull($log->fresh()->resolved_at);
        $this->get(route('admin.errors.index', ['state' => 'open']))->assertDontSee('Boom from a test');

        $this->delete(route('admin.errors.clear-resolved'))->assertRedirect();
        $this->assertDatabaseCount('error_logs', 0);

        $this->actingAs($this->userWithRole('editor'), 'web');
        $this->get(route('admin.errors.index'))->assertForbidden();
    }

    public function test_failed_jobs_can_be_viewed_retried_and_deleted(): void
    {
        Queue::fake();
        $insert = fn (string $uuid) => DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'sync', 'queue' => 'default',
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Notifications\\AdminAlert', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'attempts' => 3, 'data' => ['command' => serialize(new \stdClass)]]),
            'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: Connection refused\n#0 trace", 'failed_at' => now(),
        ]);
        $insert($a = (string) Str::uuid());
        $insert($b = (string) Str::uuid());
        $this->actingAs($this->superAdmin(), 'web');

        $this->get(route('admin.failed-jobs.index'))->assertOk()->assertSee('AdminAlert')->assertSee('Connection refused');
        $id = DB::table('failed_jobs')->where('uuid', $a)->value('id');
        $this->get(route('admin.failed-jobs.show', $id))->assertOk()->assertSee($a);

        $this->post(route('admin.failed-jobs.retry', $id))->assertRedirect(route('admin.failed-jobs.index'));
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $a]);

        $this->delete(route('admin.failed-jobs.flush'))->assertRedirect();
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $b]);
    }

    public function test_health_endpoint_command_and_dashboard(): void
    {
        $this->get('/up')->assertOk();
        $this->artisan('health:check')->expectsOutputToContain('Database')->assertSuccessful();

        $this->actingAs($this->superAdmin(), 'web');
        $this->get($this->admin())->assertOk()->assertSee('Failed jobs')->assertSee('Errors');
    }
}
