<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return ['expense_category_id' => ExpenseCategory::factory()->create()->id, 'amount' => 1500000, 'expense_date' => '2026-11-10', 'description' => 'Tagihan upstream November', ...$overrides];
    }

    public function test_lists_only_the_requested_month_with_its_total(): void
    {
        Expense::factory()->create(['expense_date' => '2026-11-03', 'amount' => 100000]);
        Expense::factory()->create(['expense_date' => '2026-11-30', 'amount' => 250000]);
        Expense::factory()->create(['expense_date' => '2026-10-31', 'amount' => 999999]);
        Expense::factory()->create(['expense_date' => '2026-12-01', 'amount' => 999999]);

        $this->actingAs($this->admin())->get('/expenses?month=2026-11')->assertInertia(
            fn (Assert $page) => $page->component('Expenses/Index')->has('expenses.data', 2)->where('total', 350000)->where('filters.month', '2026-11'),
        );
    }

    public function test_filters_by_category_and_ignores_a_malformed_month(): void
    {
        $category = ExpenseCategory::factory()->create();
        Expense::factory()->for($category)->create(['expense_date' => now(), 'amount' => 100000]);
        Expense::factory()->create(['expense_date' => now(), 'amount' => 50000]);

        $this->actingAs($this->admin())->get("/expenses?month=bukan-bulan&category_id={$category->id}")->assertInertia(
            fn (Assert $page) => $page->where('total', 100000)->where('filters.month', now()->format('Y-m')),
        );
    }

    public function test_records_expense_attributed_to_the_logged_in_user(): void
    {
        $admin = $this->admin();
        $router = Router::factory()->create();

        $this->actingAs($admin)->post('/expenses', $this->payload(['router_id' => $router->id]))->assertSessionDoesntHaveErrors();

        $expense = Expense::sole();
        $this->assertSame($admin->id, $expense->created_by);
        $this->assertSame($router->id, $expense->router_id);
        $this->assertSame(1500000, $expense->amount);
        $this->assertSame('2026-11-10', $expense->expense_date->toDateString());
    }

    public function test_validates_amount_category_and_date(): void
    {
        $this->actingAs($this->admin())->post('/expenses', ['expense_category_id' => 999, 'amount' => 0, 'expense_date' => 'kemarin', 'description' => ''])
            ->assertSessionHasErrors(['expense_category_id', 'amount', 'expense_date', 'description']);
    }

    public function test_receipt_is_stored_privately_and_downloadable_by_authorized_users_only(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/expenses', $this->payload(['receipt' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')]))->assertSessionDoesntHaveErrors();

        $expense = Expense::sole();
        Storage::disk('local')->assertExists($expense->receipt_path);
        $this->actingAs($admin)->get("/expenses/{$expense->id}/receipt")->assertOk();
        $this->actingAs($this->userWithRole('Teknisi'))->get("/expenses/{$expense->id}/receipt")->assertForbidden();
    }

    public function test_rejects_unsafe_or_oversized_receipt_files(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())->post('/expenses', $this->payload(['receipt' => UploadedFile::fake()->create('shell.php', 10, 'text/x-php')]))->assertSessionHasErrors('receipt');
        $this->actingAs($this->admin())->post('/expenses', $this->payload(['receipt' => UploadedFile::fake()->create('besar.pdf', 3000, 'application/pdf')]))->assertSessionHasErrors('receipt');
        $this->assertSame(0, Expense::count());
    }

    public function test_update_can_replace_or_remove_the_receipt(): void
    {
        Storage::fake('local');
        $expense = Expense::factory()->create(['receipt_path' => 'receipts/lama.pdf']);
        $admin = $this->admin();

        $this->actingAs($admin)->put("/expenses/{$expense->id}", $this->payload(['expense_category_id' => $expense->expense_category_id, 'receipt' => UploadedFile::fake()->image('baru.png')]));
        $this->assertNotSame('receipts/lama.pdf', $expense->fresh()->receipt_path);

        $this->actingAs($admin)->put("/expenses/{$expense->id}", $this->payload(['expense_category_id' => $expense->expense_category_id, 'remove_receipt' => true]));
        $this->assertNull($expense->fresh()->receipt_path);
    }

    public function test_update_without_receipt_fields_keeps_the_existing_receipt(): void
    {
        $expense = Expense::factory()->create(['receipt_path' => 'receipts/lama.pdf']);

        $this->actingAs($this->admin())->put("/expenses/{$expense->id}", $this->payload(['expense_category_id' => $expense->expense_category_id, 'amount' => 777]));

        $this->assertSame('receipts/lama.pdf', $expense->fresh()->receipt_path);
        $this->assertSame(777, $expense->fresh()->amount);
    }

    public function test_receipt_download_returns_404_when_there_is_no_file(): void
    {
        Storage::fake('local');
        $expense = Expense::factory()->create(['receipt_path' => null]);

        $this->actingAs($this->admin())->get("/expenses/{$expense->id}/receipt")->assertNotFound();
    }

    public function test_deleting_an_expense_is_a_soft_delete(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs($this->admin())->delete("/expenses/{$expense->id}");

        $this->assertSoftDeleted($expense);
    }
}
