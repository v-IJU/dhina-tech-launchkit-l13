<?php

namespace Tests\Feature;

use App\Core\Menus\MenuItem;
use App\Core\Menus\MenuService;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    public function test_super_admin_can_open_every_admin_page(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (['', 'users', 'users/create', 'roles', 'roles/create', 'permissions', 'menus', 'settings', 'activity', 'modules', 'backups',
            'media', 'blog/posts', 'blog/posts/create', 'blog/categories', 'pages', 'pages/create', 'inbox', 'subscribers',
            'sliders', 'services', 'testimonials', 'faqs', 'profile'] as $path) {
            $this->get($this->admin($path))->assertOk();
        }
    }

    public function test_editor_is_blocked_from_user_management(): void
    {
        $this->actingAs($this->userWithRole('editor'));

        $this->get($this->admin('users'))->assertForbidden();
        $this->get($this->admin('roles'))->assertForbidden();
        $this->get($this->admin('settings'))->assertForbidden();
        $this->get($this->admin('blog/posts'))->assertOk();
    }

    public function test_admin_cannot_manage_modules(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $this->get($this->admin('users'))->assertOk();
        $this->get($this->admin('modules'))->assertForbidden();
    }

    public function test_sidebar_only_shows_permitted_items(): void
    {
        $editor = $this->userWithRole('editor');
        $labels = collect(app(MenuService::class)->adminSidebar($editor))->flatMap(fn ($g) => collect($g['items'])->pluck('label'));

        $this->assertTrue($labels->contains('Blog'));
        $this->assertFalse($labels->contains('Users'));
        $this->assertFalse($labels->contains('Modules'));
    }

    public function test_hidden_menu_item_disappears_from_sidebar(): void
    {
        $admin = $this->superAdmin();
        $item = MenuItem::query()->where('key', 'media')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.menus.toggle', $item))->assertRedirect();

        $labels = collect(app(MenuService::class)->adminSidebar($admin))->flatMap(fn ($g) => collect($g['items'])->pluck('label'));
        $this->assertFalse($labels->contains('Media library'));
    }
}
