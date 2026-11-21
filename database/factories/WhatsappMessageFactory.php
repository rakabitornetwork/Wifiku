<?php

namespace Database\Factories;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappMessage>
 */
class WhatsappMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => fake()->numerify('628##########'),
            'type' => WhatsappMessageType::Invoice,
            'body' => fake()->paragraph(),
            'status' => WhatsappMessageStatus::Queued,
        ];
    }
}
