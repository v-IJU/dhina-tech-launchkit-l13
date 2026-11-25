<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

/**
 * Users > (user) > Permissions: extras for one person on top of their roles.
 */
class UserPermissionsTest extends TestCase
{
    public function test_one_editor_gets_an_extra_permission_others_do_not(): void
    {
        $priya = $this->userWithRole('editor');
        $other = $this->userWithRole('editor');
        $this->actingAs($this->superAdmin(), 'web');

        $this->get(route('admin.users.permissions', $priya))->assertOk()->assertSee('Role')->assertSee('Their sidebar');
        $this->put(route('admin.users.permissions.update', $priya), ['permissions' => ['customers.view', 'customers.create']])
            ->assertRedirect(route('admin.users.permissions', $priya));

        $this->assertTrue($priya->fresh()->can('customers.create'));
        $this->assertFalse($other->fresh()->can('customers.view'));
        $this->get(route('admin.users.index'))->assertSee('+2 extra');
        $this->assertDatabaseHas('activity_log', ['event' => 'permissions', 'subject_id' => $priya->id]);

        // Priya sees the Customers menu and page; the other editor doesn't.
        $this->actingAs($priya->fresh(), 'web');
        $this->get($this->admin())->assertSee(route('admin.customers.create'));
        $this->get(route('admin.customers.index'))->assertOk();
        $this->actingAs($other->fresh(), 'web');
        $this->get($this->admin())->assertDontSee(route('admin.customers.index'));
        $this->get(route('admin.customers.index'))->assertForbidden();

        // Untick everything → back to the role only.
        $this->actingAs($this->superAdmin(), 'web');
        $this->put(route('admin.users.permissions.update', $priya), ['permissions' => []]);
        $this->assertFalse($priya->fresh()->can('customers.view'));
    }

    public function test_you_can_only_grant_what_you_have(): void
    {
        $hr = Role::create(['name' => 'hr', 'guard_name' => 'web', 'display_name' => 'HR']);
        $hr->givePermissionTo(['dashboard.view', 'users.view', 'users.edit', 'users.permissions', 'faqs.delete']);
        $actor = User::factory()->create(['status' => 'active'])->assignRole('hr');
        $editor = $this->userWithRole('editor');
        $editor->givePermissionTo('settings.view'); // given earlier by a super admin
        $this->actingAs($actor, 'web');

        // customers.view: HR doesn't have it → ignored. faqs.delete: HR has it → granted.
        // settings.view: HR can't manage it → kept even though it wasn't ticked.
        $this->put(route('admin.users.permissions.update', $editor), ['permissions' => ['customers.view', 'faqs.delete']]);
        $editor = $editor->fresh();
        $this->assertTrue($editor->hasDirectPermission('faqs.delete'));
        $this->assertFalse($editor->hasDirectPermission('customers.view'));
        $this->assertTrue($editor->hasDirectPermission('settings.view'));

        // Not your own, not a super admin.
        $this->put(route('admin.users.permissions.update', $actor), ['permissions' => ['faqs.delete']])->assertForbidden();
        $this->put(route('admin.users.permissions.update', $this->superAdmin()), ['permissions' => []])->assertStatus(422);

        // Without users.permissions the page is closed.
        $this->actingAs($editor, 'web');
        $this->get(route('admin.users.permissions', $actor))->assertForbidden();
    }

    public function test_role_form_has_the_sidebar_preview_data(): void
    {
        $this->actingAs($this->superAdmin(), 'web');

        foreach ([route('admin.roles.create'), route('admin.roles.edit', Role::findByName('editor'))] as $url) {
            $html = $this->get($url)->assertOk()->assertSee('Sidebar for this role')->getContent();
            preg_match('/<script type="application\/json" data-sidebar-tree[^>]*>(.*?)<\/script>/s', $html, $m);
            $tree = json_decode($m[1] ?? '[]', true);

            $customers = collect($tree)->flatMap(fn ($g) => $g['items'])->firstWhere('label', 'Customers');
            $this->assertSame('customers.view', $customers['permission']);
            $this->assertContains('customers.create', array_column($customers['children'], 'permission'));
        }
    }
}
