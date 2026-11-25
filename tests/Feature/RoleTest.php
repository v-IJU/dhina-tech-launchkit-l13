<?php

namespace Tests\Feature;

use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleTest extends TestCase
{
    public function test_create_role_with_permissions_and_it_takes_effect(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.roles.store'), [
            'display_name' => 'Support Agent',
            'description' => 'Answers enquiries',
            'color' => '#7c3aed',
            'permissions' => ['dashboard.view', 'inbox.view', 'inbox.reply', 'not.a.permission'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::findByName('support-agent');
        $this->assertSame('Support Agent', $role->display_name);
        $this->assertEqualsCanonicalizing(['dashboard.view', 'inbox.view', 'inbox.reply'], $role->permissions->pluck('name')->all());

        $agent = $this->userWithRole('support-agent');
        $this->actingAs($agent)->get($this->admin('inbox'))->assertOk();
        $this->actingAs($agent)->get($this->admin('users'))->assertForbidden();
    }

    public function test_role_name_must_be_unique(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.roles.store'), ['display_name' => 'Editor', 'color' => '#2563eb'])->assertSessionHasErrors('name');
    }

    public function test_system_roles_and_roles_with_users_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin());

        $this->delete(route('admin.roles.destroy', Role::findByName('editor')))->assertSessionHas('error');
        $this->assertNotNull(Role::findByName('editor'));

        $custom = Role::create(['name' => 'temp', 'guard_name' => 'web', 'display_name' => 'Temp']);
        $this->userWithRole('temp');
        $this->delete(route('admin.roles.destroy', $custom))->assertSessionHas('error');

        $empty = Role::create(['name' => 'empty', 'guard_name' => 'web', 'display_name' => 'Empty']);
        $this->delete(route('admin.roles.destroy', $empty))->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['name' => 'empty']);
    }

    public function test_custom_permission_can_be_added_and_removed(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.permissions.store'), ['resource' => 'blog', 'action' => 'Feature Post'])->assertSessionHas('success');
        $permission = Permission::findByName('blog.feature-post');

        $this->get(route('admin.roles.create'))->assertSee('blog.feature-post');

        $this->delete(route('admin.permissions.destroy', $permission))->assertSessionHas('success');
        $this->assertDatabaseMissing('permissions', ['name' => 'blog.feature-post']);
    }

    public function test_module_permissions_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin());

        $this->delete(route('admin.permissions.destroy', Permission::findByName('users.view')))->assertSessionHas('error');
    }
}
