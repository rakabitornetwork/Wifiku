<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ExpenseCategoryControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_creates_category_with_unique_name_and_valid_type(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/expense-categories', ['name' => 'Listrik POP', 'type' => 'other'])->assertSessionDoesntHaveErrors();
        $this->actingAs($admin)->post('/expense-categories', ['name' => 'Listrik POP', 'type' => 'other'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/expense-categories', ['name' => 'Lain', 'type' => 'tidak-ada'])->assertSessionHasErrors('type');
    }

    public function test_renames_category_keeping_its_own_name_valid(): void
    {
        $category = ExpenseCategory::factory()->create(['name' => 'Listrik']);

        $this->actingAs($this->admin())->put("/expense-categories/{$category->id}", ['name' => 'Listrik', 'type' => 'field'])->assertSessionDoesntHaveErrors();

        $this->assertSame('field', $category->fresh()->type->value);
    }

    public function test_refuses_to_delete_category_used_by_any_expense_even_a_deleted_one(): void
    {
        $category = ExpenseCategory::factory()->create();
        Expense::factory()->for($category)->create()->delete();

        $this->actingAs($this->admin())->delete("/expense-categories/{$category->id}");

        $this->assertModelExists($category);
    }

    public function test_deletes_unused_category(): void
    {
        $category = ExpenseCategory::factory()->create();

        $this->actingAs($this->admin())->delete("/expense-categories/{$category->id}");

        $this->assertModelMissing($category);
    }
}
