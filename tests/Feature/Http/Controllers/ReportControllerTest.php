<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_profit_loss_page_defaults_to_current_month_and_includes_a_six_month_trend(): void
    {
        $this->actingAs($this->admin())->get('/reports/profit-loss')->assertInertia(
            fn (Assert $page) => $page->component('Reports/ProfitLoss')->where('report.month', now()->format('Y-m'))->has('trend', 6),
        );
    }

    public function test_profit_loss_page_honours_the_month_parameter(): void
    {
        Expense::factory()->create(['amount' => 120000, 'expense_date' => '2026-03-15']);

        $this->actingAs($this->admin())->get('/reports/profit-loss?month=2026-03')->assertInertia(
            fn (Assert $page) => $page->where('report.month', '2026-03')->where('report.expenses.total', 120000)->where('trend.5.month', '2026-03'),
        );
    }

    public function test_invalid_month_falls_back_to_the_current_month(): void
    {
        $this->actingAs($this->admin())->get('/reports/profit-loss?month=2026-13')->assertInertia(fn (Assert $page) => $page->where('report.month', now()->format('Y-m')));
        $this->actingAs($this->admin())->get('/reports/voucher-sales?month=<script>')->assertInertia(fn (Assert $page) => $page->where('month', now()->format('Y-m')));
    }

    public function test_voucher_sales_page_renders(): void
    {
        $this->actingAs($this->admin())->get('/reports/voucher-sales?month=2026-11')->assertInertia(
            fn (Assert $page) => $page->component('Reports/VoucherSales')->where('month', '2026-11')->has('report.rows', 0),
        );
    }
}
