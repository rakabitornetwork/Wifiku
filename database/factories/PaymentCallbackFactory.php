<?php

namespace Database\Factories;

use App\Enums\PaymentGatewayDriver;
use App\Models\PaymentCallback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentCallback>
 */
class PaymentCallbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver' => PaymentGatewayDriver::Tripay,
            'merchant_ref' => fake()->uuid(),
            'signature_valid' => true,
            'payload' => ['status' => 'PAID'],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
