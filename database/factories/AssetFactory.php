<?php

namespace Database\Factories;

use App\Enums\AssetOwnership;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => AssetType::Ont,
            'brand' => fake()->randomElement(['ZTE', 'Huawei', 'Fiberhome']),
            'model' => fake()->bothify('F###'),
            'serial_number' => fake()->unique()->bothify('ZTEG########'),
            'mac_address' => fake()->unique()->macAddress(),
            'ownership' => AssetOwnership::Company,
            'status' => AssetStatus::InStock,
        ];
    }
}
