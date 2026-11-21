<?php

namespace Database\Factories;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_batch_id' => VoucherBatch::factory(),
            'router_id' => fn (array $attributes) => VoucherBatch::find($attributes['voucher_batch_id'])->router_id,
            'hotspot_profile_id' => fn (array $attributes) => VoucherBatch::find($attributes['voucher_batch_id'])->hotspot_profile_id,
            'code' => fake()->unique()->bothify('??####'),
            'password' => fake()->bothify('####'),
            'price' => 5000,
            'status' => VoucherStatus::Unused,
        ];
    }
}
