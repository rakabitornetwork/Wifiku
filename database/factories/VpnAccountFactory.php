<?php

namespace Database\Factories;

use App\Enums\VpnAccountStatus;
use App\Enums\VpnProtocol;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VpnAccount>
 */
class VpnAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vpn_server_id' => VpnServer::factory(),
            'name' => fake()->name(),
            'protocol' => VpnProtocol::Sstp,
            'username' => fake()->unique()->userName(),
            'password' => fake()->password(12),
            'remote_address' => '10.99.0.'.fake()->unique()->numberBetween(2, 254),
            'status' => VpnAccountStatus::Active,
            'monthly_price' => 25000,
        ];
    }
}
