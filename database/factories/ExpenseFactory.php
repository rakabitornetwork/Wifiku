<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => fake()->numberBetween(50, 5000) * 1000,
            'expense_date' => fake()->dateTimeThisMonth(),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
