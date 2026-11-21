<?php

namespace Tests\Feature\Jobs\Voucher;

use App\Enums\VoucherBatchStatus;
use App\Jobs\Voucher\GenerateVoucherBatchJob;
use App\Models\HotspotProfile;
use App\Models\Reseller;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Voucher\VoucherCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateVoucherBatchJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(VoucherBatch $batch): void
    {
        (new GenerateVoucherBatchJob($batch))->handle(new VoucherCodeGenerator);
    }

    public function test_generates_the_requested_number_of_unique_vouchers_and_completes_the_batch(): void
    {
        $batch = VoucherBatch::factory()->create(['quantity' => 30, 'code_length' => 6, 'prefix' => 'WF']);

        $this->runJob($batch);

        $batch->refresh();
        $this->assertSame(VoucherBatchStatus::Completed, $batch->status);
        $this->assertSame(30, $batch->generated_count);
        $this->assertSame(30, $batch->vouchers()->distinct('code')->count('code'));
        $this->assertTrue($batch->vouchers()->pluck('code')->every(fn (string $code): bool => str_starts_with($code, 'WF') && strlen($code) === 8));
    }

    public function test_vouchers_inherit_router_profile_and_selling_price(): void
    {
        $profile = HotspotProfile::factory()->create(['price' => 5000]);
        $batch = VoucherBatch::factory()->for($profile)->create(['quantity' => 3]);

        $this->runJob($batch);

        $this->assertSame(3, Voucher::where(['router_id' => $profile->router_id, 'hotspot_profile_id' => $profile->id, 'price' => 5000, 'status' => 'unused'])->count());
    }

    public function test_reseller_batch_uses_profile_reseller_price(): void
    {
        $profile = HotspotProfile::factory()->create(['price' => 5000, 'reseller_price' => 4200]);
        $batch = VoucherBatch::factory()->for($profile)->for(Reseller::factory()->create(['discount_percent' => 50]))->create(['quantity' => 2]);

        $this->runJob($batch);

        $this->assertSame([4200], $batch->vouchers()->pluck('price')->unique()->all());
    }

    public function test_reseller_batch_falls_back_to_discount_percent_without_reseller_price(): void
    {
        $profile = HotspotProfile::factory()->create(['price' => 5000, 'reseller_price' => null]);
        $batch = VoucherBatch::factory()->for($profile)->for(Reseller::factory()->create(['discount_percent' => 10]))->create(['quantity' => 2]);

        $this->runJob($batch);

        $this->assertSame([4500], $batch->vouchers()->pluck('price')->unique()->all());
    }

    public function test_rerunning_only_creates_the_missing_vouchers(): void
    {
        $batch = VoucherBatch::factory()->create(['quantity' => 10, 'generated_count' => 4, 'status' => VoucherBatchStatus::Processing]);
        Voucher::factory()->count(4)->for($batch)->create();

        $this->runJob($batch);

        $this->assertSame(10, $batch->fresh()->generated_count);
        $this->assertSame(4 + 6, $batch->vouchers()->count());
    }

    public function test_completed_batch_is_left_untouched(): void
    {
        $batch = VoucherBatch::factory()->create(['quantity' => 5, 'generated_count' => 5, 'status' => VoucherBatchStatus::Completed]);

        $this->runJob($batch);

        $this->assertSame(0, $batch->vouchers()->count());
    }

    public function test_never_reuses_a_code_that_already_exists_on_the_same_router(): void
    {
        $batch = VoucherBatch::factory()->create(['quantity' => 9, 'code_length' => 1, 'charset' => 'numeric']);
        foreach (['0', '1', '2', '3', '4', '5', '6', '7', '8'] as $taken) {
            Voucher::factory()->create(['router_id' => $batch->router_id, 'code' => $taken]);
        }

        // Hanya angka "9" yang bebas, jadi batch 9 voucher tidak mungkin selesai tanpa mengulang kode.
        $this->expectException(\RuntimeException::class);

        $this->runJob($batch);
    }

    public function test_failed_hook_marks_the_batch_as_failed(): void
    {
        $batch = VoucherBatch::factory()->create();

        (new GenerateVoucherBatchJob($batch))->failed(new \RuntimeException('boom'));

        $this->assertSame(VoucherBatchStatus::Failed, $batch->fresh()->status);
    }
}
