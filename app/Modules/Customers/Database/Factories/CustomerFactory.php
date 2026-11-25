<?php

declare(strict_types=1);

namespace App\Modules\Customers\Database\Factories;

use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '9'.fake()->numerify('#########'),
            'password' => 'Secret#123',
            'status' => 'active',
            'email_verified_at' => now(),
            'marketing_opt_in' => fake()->boolean(30),
            'created_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function blocked(): static
    {
        return $this->state(['status' => 'blocked']);
    }
}
