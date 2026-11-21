<?php

namespace Database\Seeders;

use App\Enums\ExpenseType;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Bandwidth / Upstream' => ExpenseType::Bandwidth,
            'Peralatan & Perangkat' => ExpenseType::Equipment,
            'Operasional Lapangan' => ExpenseType::Field,
            'Gaji Teknisi' => ExpenseType::Salary,
            'Lain-lain' => ExpenseType::Other,
        ];

        foreach ($categories as $name => $type) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['type' => $type]);
        }
    }
}
