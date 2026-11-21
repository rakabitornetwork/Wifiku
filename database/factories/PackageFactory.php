<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Home '.fake()->randomElement([10, 20, 30, 50]).' Mbps',
            'mikrotik_profile' => fake()->unique()->bothify('PKG-##'),
            'rate_limit' => '20M/20M',
            'price' => fake()->randomElement([150000, 200000, 250000, 300000]),
            'is_active' => true,
        ];
    }
}
