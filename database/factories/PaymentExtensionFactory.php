<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PaymentExtension;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentExtension>
 */
class PaymentExtensionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'previous_until' => null,
            'granted_until' => now()->addDays(14)->toDateString(),
            'days' => 14,
            'reason' => fake()->sentence(),
            'granted_by' => User::factory(),
        ];
    }
}
