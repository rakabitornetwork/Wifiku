<?php

namespace Database\Factories;

use App\Enums\VoucherBatchStatus;
use App\Models\HotspotProfile;
use App\Models\User;
use App\Models\VoucherBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherBatch>
 */
class VoucherBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_code' => fake()->unique()->bothify('B-######'),
            'hotspot_profile_id' => HotspotProfile::factory(),
            'router_id' => fn (array $attributes) => HotspotProfile::find($attributes['hotspot_profile_id'])->router_id,
            'quantity' => 10,
            'code_length' => 6,
            'charset' => 'alphanumeric',
            'status' => VoucherBatchStatus::Queued,
            'created_by' => User::factory(),
        ];
    }
}
