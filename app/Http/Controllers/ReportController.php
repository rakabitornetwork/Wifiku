<?php

namespace App\Http\Controllers;

use App\Services\Reports\ProfitLossReport;
use App\Services\Reports\VoucherSalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function profitLoss(Request $request, ProfitLossReport $report): Response
    {
        $month = $this->month($request);

        return Inertia::render('Reports/ProfitLoss', [
            'report' => $report->forMonth($month),
            'trend' => $report->trend($month),
        ]);
    }

    public function voucherSales(Request $request, VoucherSalesReport $report): Response
    {
        $month = $this->month($request);

        return Inertia::render('Reports/VoucherSales', ['month' => $month->format('Y-m'), 'report' => $report->forMonth($month)]);
    }

    private function month(Request $request): CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfDay()
            : CarbonImmutable::today()->startOfMonth();
    }
}
