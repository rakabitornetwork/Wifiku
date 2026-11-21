<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_code' => fake()->unique()->bothify('TT-####-####'),
            'router_id' => Router::factory(),
            'package_id' => Package::factory(),
            'nik' => fake()->numerify('################'),
            'name' => fake()->name(),
            'whatsapp' => fake()->numerify('628##########'),
            'address' => fake()->address(),
            'village' => fake()->city(),
            'pppoe_username' => fake()->unique()->userName(),
            'pppoe_password' => fake()->password(8, 12),
            'billing_cycle' => BillingCycle::Fixed,
            'billing_day' => 1,
            'due_days' => 7,
            'installed_at' => fake()->dateTimeBetween('-1 year'),
            'status' => CustomerStatus::Active,
        ];
    }
}
