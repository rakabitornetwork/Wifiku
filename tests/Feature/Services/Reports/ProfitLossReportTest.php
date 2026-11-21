<?php

namespace Tests\Feature\Services\Reports;

use App\Enums\ExpenseType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VoucherStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Voucher;
use App\Services\Reports\ProfitLossReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitLossReportTest extends TestCase
{
    use RefreshDatabase;

    private function report(string $month = '2026-11'): array
    {
        return $this->app->make(ProfitLossReport::class)->forMonth(CarbonImmutable::parse("{$month}-01"));
    }

    private function payment(string $paidAt, int $amount, PaymentMethod $method = PaymentMethod::Cash, PaymentStatus $status = PaymentStatus::Paid): Payment
    {
        return Payment::factory()->create(['method' => $method, 'amount' => $amount, 'status' => $status, 'paid_at' => $paidAt]);
    }

    public function test_net_profit_is_billing_plus_vouchers_minus_expenses(): void
    {
        $this->payment('2026-11-05 10:00', 300000);
        $this->payment('2026-11-20 10:00', 200000, PaymentMethod::Gateway);
        Voucher::factory()->create(['status' => VoucherStatus::Active, 'price' => 5000, 'first_login_at' => '2026-11-12 09:00']);
        Expense::factory()->create(['amount' => 150000, 'expense_date' => '2026-11-15']);

        $report = $this->report();

        $this->assertSame(500000, $report['revenue']['billing']);
        $this->assertSame(['cash' => 300000, 'gateway' => 200000], $report['revenue']['billing_by_method']);
        $this->assertSame(5000, $report['revenue']['vouchers']);
        $this->assertSame(505000, $report['revenue']['total']);
        $this->assertSame(150000, $report['expenses']['total']);
        $this->assertSame(355000, $report['net_profit']);
        $this->assertSame(70.3, $report['margin']);
    }

    public function test_only_paid_payments_inside_the_month_count_including_boundaries(): void
    {
        $this->payment('2026-11-01 00:00:00', 100000);
        $this->payment('2026-11-30 23:59:59', 100000);
        $this->payment('2026-10-31 23:59:59', 999999);
        $this->payment('2026-12-01 00:00:00', 999999);
        $this->payment('2026-11-10 10:00', 999999, status: PaymentStatus::Pending);
        $this->payment('2026-11-10 10:00', 999999, status: PaymentStatus::Failed);

        $this->assertSame(200000, $this->report()['revenue']['billing']);
    }

    public function test_unused_and_disabled_vouchers_are_not_sales(): void
    {
        Voucher::factory()->create(['status' => VoucherStatus::Unused, 'price' => 5000, 'first_login_at' => null]);
        Voucher::factory()->create(['status' => VoucherStatus::Disabled, 'price' => 5000, 'first_login_at' => '2026-11-12']);
        Voucher::factory()->create(['status' => VoucherStatus::Expired, 'price' => 7000, 'first_login_at' => '2026-11-02']);

        $this->assertSame(7000, $this->report()['revenue']['vouchers']);
    }

    public function test_expenses_are_grouped_by_category_largest_first_and_exclude_deleted_ones(): void
    {
        $bandwidth = ExpenseCategory::factory()->create(['name' => 'Bandwidth', 'type' => ExpenseType::Bandwidth]);
        $field = ExpenseCategory::factory()->create(['name' => 'Lapangan', 'type' => ExpenseType::Field]);
        Expense::factory()->for($field)->create(['amount' => 50000, 'expense_date' => '2026-11-02']);
        Expense::factory()->for($bandwidth)->create(['amount' => 400000, 'expense_date' => '2026-11-03']);
        Expense::factory()->for($bandwidth)->create(['amount' => 100000, 'expense_date' => '2026-11-30']);
        Expense::factory()->for($bandwidth)->create(['amount' => 777777, 'expense_date' => '2026-11-04'])->delete();
        Expense::factory()->for($bandwidth)->create(['amount' => 888888, 'expense_date' => '2026-12-01']);

        $report = $this->report();

        $this->assertSame([['name' => 'Bandwidth', 'type' => 'bandwidth', 'amount' => 500000], ['name' => 'Lapangan', 'type' => 'field', 'amount' => 50000]], $report['expenses']['by_category']);
        $this->assertSame(550000, $report['expenses']['total']);
    }

    public function test_loss_is_negative_and_margin_is_unknown_without_revenue(): void
    {
        Expense::factory()->create(['amount' => 80000, 'expense_date' => '2026-11-02']);

        $report = $this->report();

        $this->assertSame(-80000, $report['net_profit']);
        $this->assertNull($report['margin']);
    }

    public function test_empty_month_is_all_zero(): void
    {
        $report = $this->report('2027-05');

        $this->assertSame(0, $report['revenue']['total']);
        $this->assertSame(0, $report['net_profit']);
        $this->assertSame([], $report['expenses']['by_category']);
    }

    public function test_trend_returns_six_months_oldest_first_ending_at_the_requested_month(): void
    {
        $this->payment('2026-09-10 10:00', 100000);
        Expense::factory()->create(['amount' => 30000, 'expense_date' => '2026-11-10']);

        $trend = $this->app->make(ProfitLossReport::class)->trend(CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'], array_column($trend, 'month'));
        $this->assertSame(100000, $trend[3]['revenue']);
        $this->assertSame(['revenue' => 0, 'expenses' => 30000, 'net_profit' => -30000], array_intersect_key($trend[5], array_flip(['revenue', 'expenses', 'net_profit'])));
    }

    public function test_trend_crosses_year_boundaries(): void
    {
        $trend = $this->app->make(ProfitLossReport::class)->trend(CarbonImmutable::parse('2027-02-01'), months: 3);

        $this->assertSame(['2026-12', '2027-01', '2027-02'], array_column($trend, 'month'));
    }
}
