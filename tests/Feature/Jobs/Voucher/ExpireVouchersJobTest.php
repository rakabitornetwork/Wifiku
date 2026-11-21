<?php

namespace Tests\Feature\Jobs\Voucher;

use App\Enums\VoucherStatus;
use App\Jobs\Voucher\ExpireVouchersJob;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Mikrotik\MikrotikConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class ExpireVouchersJobTest extends TestCase
{
    use FakesRouterOs;
    use RefreshDatabase;

    public function test_removes_expired_vouchers_from_router_and_marks_them_expired(): void
    {
        $batch = VoucherBatch::factory()->create();
        $expired = Voucher::factory()->for($batch)->create(['code' => 'OLD1', 'status' => VoucherStatus::Active, 'expires_at' => now()->subMinute()]);
        $running = Voucher::factory()->for($batch)->create(['code' => 'NEW1', 'status' => VoucherStatus::Active, 'expires_at' => now()->addHour()]);
        $fake = $this->fakeRouterOs($batch->router);
        $fake->seed('/ip/hotspot/user', ['name' => 'OLD1']);
        $fake->seed('/ip/hotspot/user', ['name' => 'NEW1']);
        $fake->seed('/ip/hotspot/active', ['user' => 'OLD1']);

        (new ExpireVouchersJob)->handle();

        $this->assertSame(VoucherStatus::Expired, $expired->fresh()->status);
        $this->assertSame(VoucherStatus::Active, $running->fresh()->status);
        $this->assertSame(['NEW1'], array_column($fake->rows('/ip/hotspot/user'), 'name'));
        $this->assertSame([], $fake->rows('/ip/hotspot/active'));
    }

    public function test_unreachable_router_keeps_vouchers_active_for_the_next_run(): void
    {
        $voucher = Voucher::factory()->create(['status' => VoucherStatus::Active, 'expires_at' => now()->subMinute()]);
        $this->fakeRouterOs($voucher->router)->failWith = new MikrotikConnectionException('timeout');

        (new ExpireVouchersJob)->handle();

        $this->assertSame(VoucherStatus::Active, $voucher->fresh()->status);
    }
}
