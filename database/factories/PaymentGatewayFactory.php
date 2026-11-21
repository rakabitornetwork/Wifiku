<?php

namespace Database\Factories;

use App\Enums\FeeBearer;
use App\Enums\PaymentGatewayDriver;
use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
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
            'name' => 'Tripay',
            'credentials' => ['api_key' => fake()->sha1(), 'private_key' => fake()->sha1(), 'merchant_code' => fake()->bothify('T#####')],
            'is_active' => false,
            'is_sandbox' => true,
            'enabled_channels' => ['QRIS'],
            'fee_bearer' => FeeBearer::Merchant,
        ];
    }
}
