<?php

namespace Tests\Feature\Jobs\Voucher;

use App\Jobs\Voucher\GenerateVoucherBatchJob;
use App\Jobs\Voucher\PushVouchersToRouterJob;
use App\Models\HotspotProfile;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Mikrotik\MikrotikConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class PushVouchersToRouterJobTest extends TestCase
{
    use FakesRouterOs;
    use RefreshDatabase;

    public function test_pushes_unsynced_vouchers_as_hotspot_users_and_marks_them_synced(): void
    {
        $batch = VoucherBatch::factory()->for(HotspotProfile::factory()->create(['mikrotik_profile' => 'HS-1D']))->create(['batch_code' => 'VC-1']);
        Voucher::factory()->count(3)->for($batch)->create();
        $fake = $this->fakeRouterOs($batch->router);

        PushVouchersToRouterJob::dispatch($batch);

        $users = $fake->rows('/ip/hotspot/user');
        $this->assertCount(3, $users);
        $this->assertSame(['HS-1D'], array_values(array_unique(array_column($users, 'profile'))));
        $this->assertSame(['vc-VC-1'], array_values(array_unique(array_column($users, 'comment'))));
        $this->assertSame(0, $batch->vouchers()->where('synced_to_router', false)->count());
    }

    public function test_resuming_after_partial_push_does_not_duplicate_users(): void
    {
        $batch = VoucherBatch::factory()->create(['batch_code' => 'VC-2']);
        $already = Voucher::factory()->for($batch)->create(['code' => 'AAAA11']);
        Voucher::factory()->for($batch)->create(['code' => 'BBBB22']);
        $fake = $this->fakeRouterOs($batch->router);
        $fake->seed('/ip/hotspot/user', ['name' => $already->code, 'comment' => 'vc-VC-2']);

        PushVouchersToRouterJob::dispatch($batch);

        $this->assertSame(['AAAA11', 'BBBB22'], array_column($fake->rows('/ip/hotspot/user'), 'name'));
    }

    public function test_router_failure_leaves_vouchers_unsynced(): void
    {
        $batch = VoucherBatch::factory()->create();
        Voucher::factory()->count(2)->for($batch)->create();
        $this->fakeRouterOs($batch->router)->failWith = new MikrotikConnectionException('timeout');

        try {
            (new PushVouchersToRouterJob($batch))->handle();
        } catch (MikrotikConnectionException) {
            // job akan dicoba ulang oleh worker
        }

        $this->assertSame(2, $batch->vouchers()->where('synced_to_router', false)->count());
    }

    public function test_generation_queues_the_push_when_complete(): void
    {
        Queue::fake([PushVouchersToRouterJob::class]);
        $batch = VoucherBatch::factory()->create(['quantity' => 2]);

        GenerateVoucherBatchJob::dispatch($batch);

        Queue::assertPushed(PushVouchersToRouterJob::class, fn ($job) => $job->voucherBatch->is($batch));
    }

    public function test_hotspot_users_are_bound_to_the_chosen_server_or_left_open_for_all(): void
    {
        $bound = VoucherBatch::factory()->create(['batch_code' => 'VC-S1', 'server' => 'hs-vlan-vcorner']);
        $open = VoucherBatch::factory()->create(['batch_code' => 'VC-S2', 'server' => null]);
        Voucher::factory()->for($bound)->create(['code' => 'BOUND1']);
        Voucher::factory()->for($open)->create(['code' => 'OPEN01']);
        $boundFake = $this->fakeRouterOs($bound->router);
        $openFake = $this->fakeRouterOs($open->router);

        PushVouchersToRouterJob::dispatch($bound);
        PushVouchersToRouterJob::dispatch($open);

        $this->assertSame('hs-vlan-vcorner', $boundFake->rows('/ip/hotspot/user')[0]['server']);
        $this->assertArrayNotHasKey('server', $openFake->rows('/ip/hotspot/user')[0]);
    }
}
