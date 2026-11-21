<?php

namespace Database\Factories;

use App\Enums\OutageStatus;
use App\Models\Odp;
use App\Models\Outage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outage>
 */
class OutageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'odp_id' => Odp::factory(),
            'title' => 'Gangguan fiber optik',
            'message' => 'Terjadi gangguan jaringan di area Anda. Teknisi sedang menangani.',
            'status' => OutageStatus::Open,
            'created_by' => User::factory(),
        ];
    }
}
