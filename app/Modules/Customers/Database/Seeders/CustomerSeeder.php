<?php

declare(strict_types=1);

namespace App\Modules\Customers\Database\Seeders;

use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * 40 demo customers with addresses. Every password is Secret#123.
 *
 *   php artisan db:seed --class="App\Modules\Customers\Database\Seeders\CustomerSeeder"
 *
 * Try the website with demo@customer.test / Secret#123.
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [['Chennai', 'Tamil Nadu', '600001'], ['Bengaluru', 'Karnataka', '560001'], ['Kochi', 'Kerala', '682001'], ['Hyderabad', 'Telangana', '500001'], ['Mumbai', 'Maharashtra', '400001']];

        Customer::query()->firstOrCreate(['email' => 'demo@customer.test'], [
            'name' => 'Demo Customer', 'password' => 'Secret#123', 'phone' => '9876543210', 'status' => 'active',
        ])->forceFill(['email_verified_at' => now()])->save();

        Customer::factory()->count(32)->create();
        Customer::factory()->count(5)->unverified()->create();
        Customer::factory()->count(3)->blocked()->create();

        Customer::query()->doesntHave('addresses')->get()->each(function (Customer $customer) use ($cities) {
            foreach (range(1, random_int(1, 2)) as $i) {
                [$city, $state, $pin] = $cities[array_rand($cities)];
                $customer->addresses()->create([
                    'label' => $i === 1 ? 'Home' : 'Office',
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'line1' => fake()->buildingNumber().', '.fake()->streetName(),
                    'city' => $city, 'state' => $state, 'postal_code' => $pin, 'country' => 'India',
                ]);
            }
        });
    }
}
