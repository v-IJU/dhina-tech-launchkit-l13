<?php

declare(strict_types=1);

namespace App\Modules\Users\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // checked by route permission middleware
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'username' => strtolower(trim((string) $this->input('username'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['female', 'male', 'other'])],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'status' => ['required', Rule::in(User::STATUSES)],
            'email_verified' => ['boolean'],
            'require_two_factor' => ['boolean'],
            'force_password_reset' => ['boolean'],
            'send_welcome' => ['boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['username.regex' => 'The username may only contain letters, numbers, dots, dashes and underscores.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $super = config('starter.super_admin_role');
            if ($this->input('role') === $super && ! $this->user()->isSuperAdmin()) {
                $validator->errors()->add('role', 'Only a super admin can give the super admin role.');
            }
            $target = $this->route('user');
            if ($target instanceof User && $target->is($this->user()) && $this->input('status') !== 'active') {
                $validator->errors()->add('status', 'You cannot deactivate your own account.');
            }
        }];
    }
}
