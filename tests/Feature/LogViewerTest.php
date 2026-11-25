<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * System > Monitoring > Log files (opcodesio/log-viewer) at /admin/logs.
 */
class LogViewerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = storage_path('logs/laravel-2026-01-15.log');
        File::put($this->file, "[2026-01-15 10:00:00] production.ERROR: Payment gateway timed out {\"order\":15}\n[2026-01-15 10:01:00] production.INFO: Order 16 paid\n");
    }

    protected function tearDown(): void
    {
        File::delete($this->file);
        parent::tearDown();
    }

    public function test_staff_with_permission_can_browse_log_files(): void
    {
        $this->actingAs($this->superAdmin(), 'web');

        $this->get(route('log-viewer.index'))->assertOk()->assertSee('Back to admin panel');
        $this->getJson(route('log-viewer.files'))->assertOk()->assertJsonFragment(['name' => 'laravel-2026-01-15.log']);

        // No CSP on the viewer (it uses an inline script); the rest of the admin panel keeps it.
        $this->get(route('log-viewer.index'))->assertHeaderMissing('Content-Security-Policy');
        $this->get($this->admin())->assertHeader('Content-Security-Policy');
    }

    public function test_access_follows_permissions(): void
    {
        // Signed out → admin sign-in page.
        $this->get(route('log-viewer.index'))->assertRedirect(route('login'));

        // Editors have no logs.* permissions.
        $this->actingAs($this->userWithRole('editor'), 'web');
        $this->get(route('log-viewer.index'))->assertForbidden();

        // View only: can read, cannot delete.
        Role::findByName('editor')->givePermissionTo('logs.view');
        $this->get(route('log-viewer.index'))->assertOk();
        $id = collect($this->getJson(route('log-viewer.files'))->json())->firstWhere('name', 'laravel-2026-01-15.log')['identifier'];
        $this->deleteJson(route('log-viewer.files.delete', $id))->assertForbidden();
        $this->assertFileExists($this->file);
    }
}
