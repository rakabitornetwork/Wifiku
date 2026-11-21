<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => fake()->unique()->bothify('INV/####/#####'),
            'customer_id' => Customer::factory(),
            'period_start' => now()->startOfMonth(),
            'period_end' => fn (array $attributes) => CarbonImmutable::parse($attributes['period_start'])->startOfDay()->addMonthNoOverflow()->subDay(),
            'issue_date' => fn (array $attributes) => CarbonImmutable::parse($attributes['period_start'])->startOfDay(),
            'due_date' => fn (array $attributes) => CarbonImmutable::parse($attributes['period_start'])->startOfDay()->addDays(7),
            'subtotal' => 200000,
            'total' => 200000,
            'status' => InvoiceStatus::Unpaid,
        ];
    }
}
