<?php

namespace Tests\Feature\Jobs\Voucher;

use App\Enums\VoucherBatchStatus;
use App\Enums\VoucherStatus;
use App\Jobs\Voucher\DeleteVouchersJob;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Mikrotik\MikrotikConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class DeleteVouchersJobTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    private VoucherBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batch = VoucherBatch::factory()->create(['status' => VoucherBatchStatus::Completed]);
        Voucher::factory()->for($this->batch)->create(['code' => 'UNU1', 'status' => VoucherStatus::Unused]);
        Voucher::factory()->for($this->batch)->create(['code' => 'ACT1', 'status' => VoucherStatus::Active]);
        $fake = $this->fakeRouterOs($this->batch->router);
        $fake->seed('/ip/hotspot/user', ['name' => 'UNU1']);
        $fake->seed('/ip/hotspot/user', ['name' => 'ACT1']);
        $fake->seed('/ip/hotspot/active', ['user' => 'ACT1']);
    }

    public function test_default_removes_only_unused_vouchers_from_router_and_database(): void
    {
        (new DeleteVouchersJob($this->batch->router_id, $this->batch->id))->handle();

        $this->assertSame(['ACT1'], Voucher::pluck('code')->all());
        $this->assertSame(['ACT1'], array_column($this->fakeRouterOs($this->batch->router)->rows('/ip/hotspot/user'), 'name'));
        $this->assertNotNull($this->batch->fresh());
    }

    public function test_including_used_removes_everything_sessions_and_the_emptied_batch(): void
    {
        (new DeleteVouchersJob($this->batch->router_id, $this->batch->id, true))->handle();

        $fake = $this->fakeRouterOs($this->batch->router);
        $this->assertSame(0, Voucher::count());
        $this->assertSame([], $fake->rows('/ip/hotspot/user'));
        $this->assertSame([], $fake->rows('/ip/hotspot/active'));
        $this->assertNull($this->batch->fresh());
    }

    public function test_router_scope_covers_all_batches_of_that_router_only(): void
    {
        $other = VoucherBatch::factory()->create(['status' => VoucherBatchStatus::Completed]);
        Voucher::factory()->for($other)->create(['status' => VoucherStatus::Unused]);
        $second = VoucherBatch::factory()->create(['router_id' => $this->batch->router_id, 'hotspot_profile_id' => $this->batch->hotspot_profile_id, 'status' => VoucherBatchStatus::Completed]);
        Voucher::factory()->for($second)->create(['code' => 'UNU2', 'status' => VoucherStatus::Unused]);

        (new DeleteVouchersJob($this->batch->router_id))->handle();

        $this->assertSame(['ACT1'], Voucher::where('router_id', $this->batch->router_id)->pluck('code')->all());
        $this->assertSame(1, Voucher::where('router_id', $other->router_id)->count());
        $this->assertNull($second->fresh());
    }

    public function test_batches_still_generating_are_not_touched(): void
    {
        $this->batch->update(['status' => VoucherBatchStatus::Processing]);

        (new DeleteVouchersJob($this->batch->router_id, $this->batch->id, true))->handle();

        $this->assertSame(2, Voucher::count());
    }

    public function test_unreachable_router_keeps_database_rows_so_the_job_can_retry(): void
    {
        $this->fakeRouterOs($this->batch->router)->failWith = new MikrotikConnectionException('timeout');

        try {
            (new DeleteVouchersJob($this->batch->router_id, $this->batch->id, true))->handle();
            $this->fail('Job seharusnya melempar exception agar dicoba ulang.');
        } catch (MikrotikConnectionException) {
            $this->assertSame(2, Voucher::count());
        }
    }

    public function test_endpoints_queue_deletion_with_scope(): void
    {
        Queue::fake([DeleteVouchersJob::class]);

        $this->actingAs($this->admin())->delete("/hotspot/batches/{$this->batch->id}/vouchers", ['include_used' => true])->assertRedirect('/hotspot/batches');
        $this->actingAs($this->admin())->delete("/hotspot/routers/{$this->batch->router_id}/vouchers")->assertRedirect();

        Queue::assertPushed(DeleteVouchersJob::class, fn ($job) => $job->batchId === $this->batch->id && $job->includeUsed === true);
        Queue::assertPushed(DeleteVouchersJob::class, fn ($job) => $job->batchId === null && $job->includeUsed === false && $job->routerId === $this->batch->router_id);
    }
}
