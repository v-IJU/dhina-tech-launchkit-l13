<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Nobody may give themselves (or anyone) more than they have. Super admins are the only exception.
 */
class PermissionEscalationTest extends TestCase
{
    /** A "manager" who may manage roles and users, but has no settings / backups rights. */
    private function manager(): User
    {
        $role = Role::query()->create(['name' => 'manager', 'guard_name' => 'web', 'display_name' => 'Manager', 'color' => '#475569']);
        $role->syncPermissions(collect(['dashboard.view', 'roles.view', 'roles.create', 'roles.edit', 'users.view', 'users.edit', 'users.create'])
            ->map(fn ($n) => Permission::findOrCreate($n, 'web')));

        return $this->userWithRole('manager');
    }

    public function test_cannot_add_permissions_to_own_role(): void
    {
        $manager = $this->manager();
        $role = Role::findByName('manager');

        $this->actingAs($manager)->put(route('admin.roles.update', $role), [
            'display_name' => 'Manager', 'color' => '#475569',
            'permissions' => ['dashboard.view', 'roles.view', 'roles.edit', 'users.view', 'users.edit', 'settings.edit', 'backups.view'],
        ])->assertForbidden();

        $this->assertFalse($role->fresh()->hasPermissionTo('settings.edit'));
    }

    private function helperRole(): Role
    {
        $role = Role::query()->create(['name' => 'helper', 'guard_name' => 'web', 'display_name' => 'Helper', 'color' => '#0891b2']);
        $role->givePermissionTo(Permission::findOrCreate('users.view', 'web'));

        return $role;
    }

    public function test_can_only_grant_permissions_they_have(): void
    {
        $manager = $this->manager();
        $helper = $this->helperRole();

        // a smaller role: may add what they have themselves; anything else is ignored
        $this->actingAs($manager)->put(route('admin.roles.update', $helper), [
            'display_name' => 'Helper', 'color' => '#0891b2', 'permissions' => ['users.view', 'users.edit', 'settings.edit'],
        ])->assertRedirect();
        $this->assertSame(['users.edit', 'users.view'], $helper->fresh()->permissions->pluck('name')->sort()->values()->all());

        // a new role: same rule
        $this->actingAs($manager)->post(route('admin.roles.store'), [
            'display_name' => 'Sneaky', 'color' => '#db2777', 'permissions' => ['users.view', 'settings.edit'],
        ])->assertRedirect();
        $this->assertSame(['users.view'], Role::findByName('sneaky')->permissions->pluck('name')->all());

        // a bigger role (Editor has pages, FAQs… the manager hasn't): can't touch it at all
        $this->actingAs($manager)->get(route('admin.roles.edit', Role::findByName('editor')))->assertForbidden();
        $this->actingAs($manager)->put(route('admin.roles.update', Role::findByName('editor')), ['display_name' => 'Editor', 'color' => '#0891b2', 'permissions' => []])->assertForbidden();

        // the form shows the boxes they can't give as disabled
        $this->actingAs($manager)->get(route('admin.roles.edit', $helper))->assertSee('value="settings.edit"', false)->assertSee("you can't give it", false);
    }

    public function test_cannot_change_own_role_or_give_a_bigger_role(): void
    {
        $manager = $this->manager();
        $this->helperRole();
        $data = ['first_name' => 'Mia', 'last_name' => 'Manager', 'email' => $manager->email, 'username' => $manager->username, 'status' => 'active'];

        // own role → admin: refused
        $this->actingAs($manager)->put(route('admin.users.update', $manager), $data + ['role' => 'admin'])->assertSessionHasErrors('role');
        $this->assertTrue($manager->fresh()->hasRole('manager'));
        // saving their own profile with the same role still works
        $this->actingAs($manager)->put(route('admin.users.update', $manager), $data + ['role' => 'manager'])->assertSessionHasNoErrors();

        $helperUser = $this->userWithRole('helper');
        // another user → admin (more than the manager has): refused
        $this->actingAs($manager)->put(route('admin.users.update', $helperUser), ['first_name' => 'H', 'last_name' => 'U', 'email' => $helperUser->email, 'username' => $helperUser->username, 'status' => 'active', 'role' => 'admin'])
            ->assertSessionHasErrors('role');
        // a new user with a bigger role: refused
        $this->actingAs($manager)->post(route('admin.users.store'), ['first_name' => 'New', 'last_name' => 'Admin', 'email' => 'new@example.com', 'username' => 'newadmin', 'status' => 'active', 'role' => 'admin', 'password' => 'Secret#12345', 'password_confirmation' => 'Secret#12345'])
            ->assertSessionHasErrors('role');
        // a smaller role is fine
        $this->actingAs($manager)->put(route('admin.users.update', $helperUser), ['first_name' => 'H', 'last_name' => 'U', 'email' => $helperUser->email, 'username' => $helperUser->username, 'status' => 'active', 'role' => 'helper'])
            ->assertSessionHasNoErrors();
        // the role list only offers roles they could give
        $this->actingAs($manager)->get(route('admin.users.create'))->assertDontSee('value="admin"', false)->assertSee('value="helper"', false);
    }

    public function test_cannot_edit_or_take_over_a_bigger_account(): void
    {
        $manager = $this->manager();
        $manager->givePermissionTo(Permission::findOrCreate('users.impersonate', 'web'), Permission::findOrCreate('users.delete', 'web'));
        $admin = $this->userWithRole('admin');

        // changing an admin's email or password = taking over the account
        $this->actingAs($manager)->get(route('admin.users.edit', $admin))->assertForbidden();
        $this->actingAs($manager)->put(route('admin.users.update', $admin), ['first_name' => 'A', 'last_name' => 'D', 'email' => 'mine@example.com', 'username' => $admin->username, 'status' => 'active', 'role' => 'manager'])
            ->assertForbidden();
        $this->assertNotSame('mine@example.com', $admin->fresh()->email);
        $this->actingAs($manager)->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.users.impersonate', $admin))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.users.bulk'), ['action' => 'delete', 'ids' => [$admin->id]]);
        $this->assertNotSoftDeleted($admin);
    }

    public function test_user_import_follows_the_same_rules(): void
    {
        $manager = $this->manager();
        $manager->givePermissionTo(Permission::findOrCreate('users.import', 'web'));
        $this->helperRole();
        $admin = $this->userWithRole('admin');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('users.csv', "First name,Email,Username,Role\n"
            ."Ok,ok@example.com,okuser,helper\n"
            ."Boss,boss@example.com,bossuser,admin\n"
            ."Taken,{$admin->email},{$admin->username},helper\n");

        $this->actingAs($manager)->post(route('admin.users.import.store'), ['file' => $file])
            ->assertSessionHas('success', 'Import finished: 1 created, 2 failed.');
        $this->assertFalse(User::query()->where('email', 'boss@example.com')->exists());
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_super_admin_and_admin_are_not_limited(): void
    {
        $boss = $this->superAdmin();
        $editor = Role::findByName('editor');

        $this->actingAs($boss)->put(route('admin.roles.update', $editor), ['display_name' => 'Editor', 'color' => '#0891b2', 'permissions' => ['settings.edit']])->assertRedirect();
        $this->assertTrue($editor->fresh()->hasPermissionTo('settings.edit'));
    }
}
