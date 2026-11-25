<?php

declare(strict_types=1);

namespace App\Modules\Customers\Requests;

use App\Modules\Customers\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admin panel: Customers > Add / Edit.
 */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission middleware on the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer)],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_keys(Customer::STATUSES))],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Customer::GENDERS))],
            'locale' => ['nullable', Rule::in(array_keys(config('starter.locales')))],
            'marketing_opt_in' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'email_verified' => ['boolean'],
            'send_invite' => ['boolean'],
            // Optional: leave empty and tick "Send set-password email" instead.
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }
}
