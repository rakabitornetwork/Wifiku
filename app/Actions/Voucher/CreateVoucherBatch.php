<?php

namespace App\Actions\Voucher;

use App\Enums\VoucherBatchStatus;
use App\Jobs\Voucher\GenerateVoucherBatchJob;
use App\Models\HotspotProfile;
use App\Models\User;
use App\Models\VoucherBatch;
use Illuminate\Support\Str;

class CreateVoucherBatch
{
    /**
     * Catat batch baru lalu serahkan pembuatan kodenya ke Queue agar UI tidak menunggu.
     *
     * @param  array{hotspot_profile_id: int, reseller_id?: int|null, quantity: int, prefix?: string|null, code_length: int, charset: string, server?: string|null}  $data
     */
    public function handle(array $data, User $creator): VoucherBatch
    {
        $profile = HotspotProfile::findOrFail($data['hotspot_profile_id']);

        $batch = VoucherBatch::create([
            ...$data,
            'batch_code' => $this->uniqueBatchCode(),
            'router_id' => $profile->router_id,
            'status' => VoucherBatchStatus::Queued,
            'created_by' => $creator->id,
        ]);

        GenerateVoucherBatchJob::dispatch($batch);

        return $batch;
    }

    private function uniqueBatchCode(): string
    {
        do {
            $code = 'VC-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (VoucherBatch::where('batch_code', $code)->exists());

        return $code;
    }
}
