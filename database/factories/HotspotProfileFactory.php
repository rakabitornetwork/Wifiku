<?php

namespace Database\Factories;

use App\Models\HotspotProfile;
use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotspotProfile>
 */
class HotspotProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'router_id' => Router::factory(),
            'name' => fake()->unique()->bothify('Voucher ## Jam'),
            'mikrotik_profile' => fake()->bothify('HS-##'),
            'price' => 5000,
            'reseller_price' => 4000,
            'validity_minutes' => 1440,
            'shared_users' => 1,
            'is_active' => true,
        ];
    }
}
