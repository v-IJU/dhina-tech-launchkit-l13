<?php

declare(strict_types=1);

namespace App\Modules\Customers\Imports;

use App\Core\Import\ModuleImport;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Notifications\CustomerResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Customers → Import (Excel or CSV). A row whose email exists updates it, other rows are added.
 * Big files run in the background in chunks; see App\Core\Import\ModuleImport for what the base does.
 */
class CustomersImport extends ModuleImport
{
    protected ?string $model = Customer::class;

    protected string $matchBy = 'email';

    /** Columns of the file (headings) with an example, for the template and the help table. */
    public function columns(): array
    {
        return [
            'name' => 'Anita Raj',
            'email' => 'anita@example.com',
            'phone' => '9876543210',
            'status' => 'active',
            'marketing_opt_in' => 'No',
            'notes' => '',
        ];
    }

    public function rules(?Model $existing): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($existing?->getKey())],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(array_keys(Customer::STATUSES))],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Emails are matched without caring about capitals. */
    public function find(array $row): ?Model
    {
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        return $email === '' ? null : Customer::query()->where('email', $email)->first();
    }

    public function saveRow(array $data, ?Model $existing): void
    {
        $data['email'] = strtolower((string) ($data['email'] ?? $existing?->getAttribute('email')));

        if ($existing !== null) {
            $existing->update($data);

            return;
        }

        // New customer: random password + "set your password" email (not while only checking the file).
        $customer = new Customer($data + ['status' => 'active']);
        $customer->password = Str::password(32);
        $customer->email_verified_at = now();
        $customer->save();
        if (! $this->dryRun()) {
            $customer->notify(new CustomerResetPassword(Password::broker('customers')->createToken($customer), newAccount: true));
        }
    }
}
