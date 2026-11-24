<?php

namespace Database\Factories;

use App\Models\VpnMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VpnMember>
 */
class VpnMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'whatsapp' => '628'.fake()->numerify('#########'),
            'password' => 'password-member',
            'is_active' => true,
        ];
    }
}
