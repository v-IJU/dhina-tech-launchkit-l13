<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Generates a real module, checks every file, then removes it again.
 */
class ModuleGeneratorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = app_path('Modules/TestGadgets');
        File::deleteDirectory($this->path);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);
        $neon = base_path('phpstan.neon');
        File::put($neon, str_replace("        - app/Modules/TestGadgets/Database/Migrations\n", '', File::get($neon)));
        parent::tearDown();
    }

    public function test_make_module_creates_a_complete_module(): void
    {
        $this->artisan('make:module', [
            'name' => 'TestGadget',
            '--fields' => 'name:string,sku:string:unique,price:decimal,notes:text:nullable,photo:image,body:editor:nullable,category_id:foreignId:nullable,is_active:boolean',
            '--web' => true,
        ])->assertSuccessful();

        foreach ([
            'module.json', 'Models/TestGadget.php', 'Controllers/Admin/TestGadgetController.php', 'Controllers/Web/TestGadgetController.php',
            'Requests/StoreTestGadgetRequest.php', 'Requests/UpdateTestGadgetRequest.php', 'Routes/admin.php', 'Routes/web.php',
            'Views/admin/index.blade.php', 'Views/admin/create.blade.php', 'Views/admin/edit.blade.php', 'Views/admin/_form.blade.php',
            'Views/web/index.blade.php', 'Views/web/show.blade.php', 'Database/Seeders/TestGadgetSeeder.php',
        ] as $file) {
            $this->assertFileExists("{$this->path}/{$file}");
        }

        // Every generated PHP file must be valid PHP.
        foreach (File::allFiles($this->path) as $file) {
            if (str_ends_with($file->getFilename(), '.php') && ! str_ends_with($file->getFilename(), '.blade.php')) {
                exec('php -l '.escapeshellarg($file->getPathname()), $out, $code);
                $this->assertSame(0, $code, "Syntax error in {$file->getFilename()}");
            }
        }

        $json = json_decode(File::get("{$this->path}/module.json"), true);
        $this->assertSame('test-gadgets', $json['slug']);
        $this->assertSame(['view', 'create', 'edit', 'delete', 'export', 'import'], $json['permissions'][0]['actions']);

        $request = File::get("{$this->path}/Requests/StoreTestGadgetRequest.php");
        $this->assertStringContainsString("Rule::unique('test_gadgets', 'sku')", $request);
        $this->assertStringContainsString("'exists:categories,id'", $request);

        // Permissions and the sidebar item are created straight away.
        $this->assertDatabaseHas('permissions', ['name' => 'test-gadgets.create']);
        $this->assertDatabaseHas('menu_items', ['key' => 'test-gadgets', 'route' => 'admin.test-gadgets.index']);

        app(ModuleManager::class)->refresh();
        $this->assertNotNull(app(ModuleManager::class)->find('test-gadgets'));
    }

    public function test_invalid_input_is_refused(): void
    {
        $this->artisan('make:module', ['name' => 'TestGadget', '--fields' => 'name:money'])->assertFailed();
        $this->artisan('make:module', ['name' => '9lives'])->assertFailed();
        $this->assertDirectoryDoesNotExist($this->path);
    }

    public function test_module_enable_and_disable_commands(): void
    {
        $this->artisan('module:disable', ['module' => 'blog'])->assertSuccessful();
        app(ModuleManager::class)->refresh();
        $this->assertFalse(app(ModuleManager::class)->isEnabled('blog'));

        $this->artisan('module:enable', ['module' => 'blog'])->assertSuccessful();
        app(ModuleManager::class)->refresh();
        $this->assertTrue(app(ModuleManager::class)->isEnabled('blog'));

        $this->artisan('module:disable', ['module' => 'users'])->assertFailed();
    }
}
