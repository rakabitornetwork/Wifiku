<?php

namespace App\Services\Reports;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Penjualan voucher bulanan per profil dan reseller. Voucher dianggap terjual saat login pertama.
 */
class VoucherSalesReport
{
    /**
     * @return array{total_count: int, total_revenue: int, rows: list<array{profile: string, router: string, reseller: string|null, count: int, revenue: int}>}
     */
    public function forMonth(CarbonImmutable $month): array
    {
        $rows = Voucher::query()
            ->join('hotspot_profiles', 'hotspot_profiles.id', '=', 'vouchers.hotspot_profile_id')
            ->join('routers', 'routers.id', '=', 'vouchers.router_id')
            ->join('voucher_batches', 'voucher_batches.id', '=', 'vouchers.voucher_batch_id')
            ->leftJoin('resellers', 'resellers.id', '=', 'voucher_batches.reseller_id')
            ->whereIn('vouchers.status', [VoucherStatus::Active, VoucherStatus::Expired])
            ->whereBetween('vouchers.first_login_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->groupBy('hotspot_profiles.id', 'hotspot_profiles.name', 'routers.name', 'resellers.id', 'resellers.name')
            ->orderByDesc(DB::raw('sum(vouchers.price)'))
            ->get([
                'hotspot_profiles.name as profile',
                'routers.name as router',
                'resellers.name as reseller',
                DB::raw('count(*) as sold'),
                DB::raw('sum(vouchers.price) as revenue'),
            ])
            ->map(fn ($row): array => [
                'profile' => $row->profile,
                'router' => $row->router,
                'reseller' => $row->reseller,
                'count' => (int) $row->sold,
                'revenue' => (int) $row->revenue,
            ])
            ->all();

        return [
            'total_count' => array_sum(array_column($rows, 'count')),
            'total_revenue' => array_sum(array_column($rows, 'revenue')),
            'rows' => $rows,
        ];
    }
}
