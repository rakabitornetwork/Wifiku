<?php

namespace Database\Factories;

use App\Models\Odp;
use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Odp>
 */
class OdpFactory extends Factory
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
            'name' => 'ODP '.fake()->streetName(),
            'code' => fake()->unique()->bothify('ODP-###-??'),
            'latitude' => fake()->latitude(-8, -6),
            'longitude' => fake()->longitude(106, 114),
            'total_ports' => 8,
        ];
    }
}
