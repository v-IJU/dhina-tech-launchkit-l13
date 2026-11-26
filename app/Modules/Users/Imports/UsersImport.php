<?php

declare(strict_types=1);

namespace App\Modules\Users\Imports;

use App\Core\Import\ModuleImport;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WelcomeUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Users → Import (Excel or CSV). A row whose email exists updates it, other rows are added.
 * Big files run in the background in chunks; see App\Core\Import\ModuleImport for what the base does.
 */
class UsersImport extends ModuleImport
{
    protected ?string $model = User::class;

    protected string $matchBy = 'email';

    /** Columns of the file (headings) with an example, for the template and the help table. */
    public function columns(): array
    {
        return [
            'first_name' => 'Anita',
            'last_name' => 'Raj',
            'email' => 'anita@example.com',
            'username' => 'anita',
            'phone' => '',
            'role' => 'editor',
            'status' => 'active',
            'department' => '',
            'job_title' => '',
        ];
    }

    public function rules(?Model $existing): array
    {
        return [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existing?->getKey())],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/i', Rule::unique('users', 'username')->ignore($existing?->getKey())],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($this->roles())],
            'status' => ['nullable', Rule::in(User::STATUSES)],
            'department' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** Emails are matched without caring about capitals. */
    public function find(array $row): ?Model
    {
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        return $email === '' ? null : User::query()->where('email', $email)->first();
    }

    /**
     * Roles that may be given here (super admin only by a super admin).
     *
     * @return list<string>
     */
    private function roles(): array
    {
        $mayGiveSuperAdmin = (bool) $this->importer()?->isSuperAdmin();

        return Role::query()->pluck('name')
            ->reject(fn ($name) => $name === config('starter.super_admin_role') && ! $mayGiveSuperAdmin)
            ->values()->all();
    }

    public function saveRow(array $data, ?Model $existing): void
    {
        $role = $data['role'] ?? null;
        unset($data['role']);
        $data['email'] = strtolower((string) ($data['email'] ?? $existing?->getAttribute('email')));
        if (isset($data['username'])) {
            $data['username'] = strtolower($data['username']);
        }
        $data['name'] = trim(($data['first_name'] ?? $existing?->getAttribute('first_name')).' '.($data['last_name'] ?? $existing?->getAttribute('last_name')));

        if ($existing instanceof User) {
            if ($existing->isSuperAdmin() && ! $this->importer()?->isSuperAdmin()) {
                throw new \RuntimeException('Only a super admin can change a super admin.');
            }
            $existing->update($data);
            $user = $existing;
        } else {
            // New user: random password, must set their own via the welcome email.
            $user = User::query()->create($data + ['status' => 'active', 'password' => Hash::make(Str::password(32)), 'force_password_reset' => true]);
            if (! $this->dryRun()) {
                $user->notify(new WelcomeUser(Password::broker()->createToken($user)));
            }
        }

        if ($role) {
            $user->syncRoles([$role]);
        }
    }
}
