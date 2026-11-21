<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Enums\VoucherStatus;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Laporan laba rugi bulanan: pendapatan (tagihan lunas + voucher terjual) dikurangi pengeluaran OPEX.
 * Pendapatan dicatat saat uang diterima (paid_at) dan voucher saat login pertama (terjual/terpakai).
 */
class ProfitLossReport
{
    /**
     * @return array{month: string, revenue: array{billing: int, billing_by_method: array<string, int>, vouchers: int, total: int}, expenses: array{total: int, by_category: list<array{name: string, type: string, amount: int}>}, net_profit: int, margin: float|null}
     */
    public function forMonth(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $byMethod = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $billing = array_sum($byMethod);
        $vouchers = (int) Voucher::query()
            ->whereIn('status', [VoucherStatus::Active, VoucherStatus::Expired])
            ->whereBetween('first_login_at', [$start, $end])
            ->sum('price');

        $categories = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereNull('expenses.deleted_at')
            ->whereBetween('expenses.expense_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.type')
            ->orderByDesc(DB::raw('sum(expenses.amount)'))
            ->get(['expense_categories.name', 'expense_categories.type', DB::raw('sum(expenses.amount) as amount')])
            ->map(fn ($row): array => ['name' => $row->name, 'type' => (string) $row->type, 'amount' => (int) $row->amount])
            ->all();

        $expenseTotal = array_sum(array_column($categories, 'amount'));
        $revenue = $billing + $vouchers;

        return [
            'month' => $start->format('Y-m'),
            'revenue' => ['billing' => $billing, 'billing_by_method' => $byMethod, 'vouchers' => $vouchers, 'total' => $revenue],
            'expenses' => ['total' => $expenseTotal, 'by_category' => $categories],
            'net_profit' => $revenue - $expenseTotal,
            'margin' => $revenue > 0 ? round(($revenue - $expenseTotal) / $revenue * 100, 1) : null,
        ];
    }

    /**
     * Ringkasan beberapa bulan terakhir (terlama lebih dulu) untuk grafik tren.
     *
     * @return list<array{month: string, revenue: int, expenses: int, net_profit: int}>
     */
    public function trend(CarbonImmutable $upTo, int $months = 6): array
    {
        $rows = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $report = $this->forMonth($upTo->startOfMonth()->subMonthsNoOverflow($i));
            $rows[] = ['month' => $report['month'], 'revenue' => $report['revenue']['total'], 'expenses' => $report['expenses']['total'], 'net_profit' => $report['net_profit']];
        }

        return $rows;
    }
}
