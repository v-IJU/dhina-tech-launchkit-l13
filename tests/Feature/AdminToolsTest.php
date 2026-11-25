<?php

namespace Tests\Feature;

use App\Core\Menus\MenuItem;
use App\Core\Settings\SettingsRepository;
use App\Modules\Media\Models\MediaItem;
use App\Modules\Pages\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminToolsTest extends TestCase
{
    public function test_settings_are_saved_and_used(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.settings.update', 'general'), [
            'site_name' => 'Acme Corp', 'site_tagline' => 'We build things', 'contact_email' => 'hi@acme.test',
        ])->assertRedirect(route('admin.settings.edit', ['tab' => 'general']));

        $this->assertSame('Acme Corp', setting('site_name'));
        $this->get($this->admin())->assertSee('Acme Corp');
    }

    public function test_settings_validation(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.settings.update', 'general'), ['site_name' => '', 'contact_email' => 'nope'])
            ->assertSessionHasErrors(['site_name', 'contact_email']);
    }

    public function test_media_upload_and_picker(): void
    {
        Storage::fake('uploads');
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('team photo.jpg', 800, 600)]])->assertRedirect();

        $item = MediaItem::query()->firstOrFail();
        $this->assertSame('image', $item->type);
        $this->assertNotEmpty($item->url());

        $this->getJson(route('admin.media.picker'))->assertOk()->assertJsonPath('data.0.name', 'team photo');
    }

    public function test_svg_and_php_uploads_are_rejected(): void
    {
        Storage::fake('uploads');
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->create('evil.php', 1, 'application/x-php')]])->assertSessionHasErrors('files.0');
        $this->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')]])->assertSessionHasErrors('files.0');
    }

    public function test_website_menu_item_can_be_added_and_reordered(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.menus.store'), ['location' => 'header', 'label' => 'Careers', 'url' => '/careers'])->assertSessionHas('success');
        $this->post(route('admin.menus.store'), ['location' => 'header', 'label' => 'Bad', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');

        $ids = MenuItem::query()->where('location', 'header')->orderByDesc('id')->pluck('id')->all();
        $this->postJson(route('admin.menus.sort'), ['ids' => $ids])->assertOk();
        $this->assertSame(1, MenuItem::query()->find($ids[0])->sort_order);
    }

    public function test_activity_log_records_changes(): void
    {
        $this->actingAs($this->superAdmin())->post(route('admin.roles.store'), ['display_name' => 'Auditor', 'color' => '#2563eb']);

        $this->get(route('admin.activity.index'))->assertOk()->assertSee('Created role Auditor');
    }

    public function test_only_allowlisted_tools_can_run(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.backups.tool', 'cache'))->assertSessionHas('success');
        $this->post(route('admin.backups.tool', 'migrate:fresh'))->assertNotFound();
    }

    public function test_backup_download_rejects_paths_outside_backup_folder(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.backups.download', ['path' => '../.env']))->assertNotFound();
        $this->get(route('admin.backups.download', ['path' => config('backup.backup.name').'/../../.env']))->assertNotFound();
    }

    public function test_brand_colour_setting_recolours_admin_and_website(): void
    {
        Page::query()->create(['title' => 'Brand', 'slug' => 'brand', 'status' => 'published', 'blocks' => []]);
        $this->get('/brand')->assertDontSee('--color-brand-600:', false); // default colour: no override

        app(SettingsRepository::class)->set(['brand_color' => '#16a34a']);

        $this->get('/brand')->assertSee('--color-brand-600:#16a34a', false);
        $this->actingAs($this->superAdmin())->get($this->admin())->assertSee('--app-brand:#16a34a', false);
    }
}
