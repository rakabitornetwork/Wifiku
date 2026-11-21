<?php

namespace Database\Factories;

use App\Enums\RouterStatus;
use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Router>
 */
class RouterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'POP '.fake()->city(),
            'host' => fake()->unique()->localIpv4(),
            'api_port' => 8728,
            'use_ssl' => false,
            'username' => 'admin',
            'password' => fake()->password(),
            'isolir_profile' => 'ISOLIR',
            'status' => RouterStatus::Unknown,
        ];
    }
}
