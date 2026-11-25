<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Roles, permissions, menus and settings are seeded for every test. */
    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::query()->create($attributes + [
            'name' => ucfirst($role).' User',
            'first_name' => ucfirst($role),
            'last_name' => 'User',
            'username' => $role.'-'.uniqid(),
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => Hash::make('Secret#123'),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function superAdmin(): User
    {
        return $this->userWithRole('super-admin');
    }

    protected function admin(string $path = ''): string
    {
        return '/'.trim(config('starter.admin_path').'/'.ltrim($path, '/'), '/');
    }
}
