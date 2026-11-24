<?php

namespace Database\Factories;

use App\Models\Router;
use App\Models\VpnServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VpnServer>
 */
class VpnServerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'router_id' => Router::factory(),
            'name' => 'VPN '.fake()->city(),
            'public_host' => fake()->domainName(),
            'local_address' => '10.99.0.1',
            'pool_start' => '10.99.0.2',
            'pool_end' => '10.99.0.254',
            'ipsec_secret' => fake()->password(12),
            'use_ipsec' => true,
            'sstp_port' => 443,
            'port_block_offset' => 122,
            'l2tp_enabled' => true,
            'sstp_enabled' => true,
            'is_active' => true,
        ];
    }
}
