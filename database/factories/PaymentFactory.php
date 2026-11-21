<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'customer_id' => fn (array $attributes) => Invoice::find($attributes['invoice_id'])->customer_id,
            'method' => PaymentMethod::Cash,
            'merchant_ref' => fake()->unique()->uuid(),
            'amount' => 200000,
            'status' => PaymentStatus::Pending,
        ];
    }
}
