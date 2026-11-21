<?php

namespace Tests\Feature\Services\Reports;

use App\Enums\VoucherStatus;
use App\Models\HotspotProfile;
use App\Models\Reseller;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Reports\VoucherSalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherSalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_sold_vouchers_by_profile_and_reseller(): void
    {
        $profile = HotspotProfile::factory()->create(['name' => '1 Hari']);
        $reseller = Reseller::factory()->create(['name' => 'Warung Budi']);
        $direct = VoucherBatch::factory()->for($profile)->create();
        $viaReseller = VoucherBatch::factory()->for($profile)->for($reseller)->create();
        Voucher::factory()->count(3)->for($direct)->create(['status' => VoucherStatus::Active, 'price' => 5000, 'first_login_at' => '2026-11-05']);
        Voucher::factory()->count(2)->for($viaReseller)->create(['status' => VoucherStatus::Expired, 'price' => 4000, 'first_login_at' => '2026-11-06']);
        Voucher::factory()->for($direct)->create(['status' => VoucherStatus::Unused, 'price' => 5000]);
        Voucher::factory()->for($direct)->create(['status' => VoucherStatus::Active, 'price' => 5000, 'first_login_at' => '2026-10-31 23:00']);

        $report = $this->app->make(VoucherSalesReport::class)->forMonth(CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(5, $report['total_count']);
        $this->assertSame(23000, $report['total_revenue']);
        $this->assertSame([
            ['profile' => '1 Hari', 'router' => $profile->router->name, 'reseller' => null, 'count' => 3, 'revenue' => 15000],
            ['profile' => '1 Hari', 'router' => $profile->router->name, 'reseller' => 'Warung Budi', 'count' => 2, 'revenue' => 8000],
        ], $report['rows']);
    }

    public function test_empty_month_has_no_rows(): void
    {
        $report = $this->app->make(VoucherSalesReport::class)->forMonth(CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(['total_count' => 0, 'total_revenue' => 0, 'rows' => []], $report);
    }
}
