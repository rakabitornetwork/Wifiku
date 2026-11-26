<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RouterStatus;
use App\Enums\VoucherStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Router;
use App\Models\Voucher;
use App\Services\Dashboard\DashboardWidgets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardWidgets $widgets): Response
    {
        $customersByStatus = Customer::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $unpaid = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->selectRaw('count(*) as total, coalesce(sum(total - amount_paid), 0) as outstanding')
            ->first();

        return Inertia::render('Dashboard', [
            'stats' => [
                'customers_active' => (int) ($customersByStatus[CustomerStatus::Active->value] ?? 0),
                'customers_isolated' => (int) ($customersByStatus[CustomerStatus::Isolated->value] ?? 0),
                'customers_inactive' => (int) ($customersByStatus[CustomerStatus::Inactive->value] ?? 0),
                'routers_online' => Router::where('status', RouterStatus::Online)->count(),
                'routers_total' => Router::count(),
                'invoices_unpaid' => (int) $unpaid->total,
                'outstanding' => (int) $unpaid->outstanding,
                'vouchers_unused' => Voucher::where('status', VoucherStatus::Unused)->count(),
            ],
            'widgets' => $widgets->for($request->user()),
            'routers' => Router::query()->orderBy('name')->get(['id', 'name', 'host', 'status', 'last_checked_at']),
        ]);
    }
}
