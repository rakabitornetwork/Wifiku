<?php

namespace App\Jobs\Voucher;

use App\Enums\VoucherBatchStatus;
use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Voucher\VoucherCodeGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Membuat kode voucher untuk satu batch dan memperbarui generated_count sebagai progres.
 * Aman diulang: hanya membuat sisa voucher yang belum tergenerate.
 */
class GenerateVoucherBatchJob implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 500;

    private const MAX_COLLISION_ROUNDS = 10;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(public VoucherBatch $voucherBatch) {}

    /**
     * Execute the job.
     */
    public function handle(VoucherCodeGenerator $generator): void
    {
        $batch = $this->voucherBatch->fresh(['hotspotProfile', 'reseller']);

        if ($batch === null || $batch->status === VoucherBatchStatus::Completed) {
            return;
        }

        $batch->update(['status' => VoucherBatchStatus::Processing]);

        $price = $this->unitPrice($batch);
        $collisionRounds = 0;

        while (($remaining = $batch->quantity - $batch->generated_count) > 0) {
            $codes = $this->freshCodes($batch, $generator, min(self::CHUNK_SIZE, $remaining));

            if ($codes === []) {
                if (++$collisionRounds >= self::MAX_COLLISION_ROUNDS) {
                    throw new RuntimeException('Ruang kode voucher hampir habis; perpanjang kode atau ganti karakter.');
                }

                continue;
            }

            $now = now();

            Voucher::insert(array_map(fn (string $code): array => [
                'voucher_batch_id' => $batch->id,
                'router_id' => $batch->router_id,
                'hotspot_profile_id' => $batch->hotspot_profile_id,
                'code' => $code,
                'password' => $code,
                'price' => $price,
                'status' => VoucherStatus::Unused->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $codes));

            $batch->increment('generated_count', count($codes));
        }

        $batch->update(['status' => VoucherBatchStatus::Completed]);

        PushVouchersToRouterJob::dispatch($batch);
    }

    /**
     * Tandai batch gagal agar terlihat di UI.
     */
    public function failed(Throwable $exception): void
    {
        $this->voucherBatch->update(['status' => VoucherBatchStatus::Failed]);
    }

    /**
     * Harga jual per voucher; reseller memakai harga reseller atau potongan persentasenya.
     */
    private function unitPrice(VoucherBatch $batch): int
    {
        $profile = $batch->hotspotProfile;

        if ($batch->reseller === null) {
            return $profile->price;
        }

        return $profile->reseller_price
            ?? (int) round($profile->price * (100 - $batch->reseller->discount_percent) / 100);
    }

    /**
     * Kode unik dalam chunk dan belum dipakai pada router yang sama.
     *
     * @return list<string>
     */
    private function freshCodes(VoucherBatch $batch, VoucherCodeGenerator $generator, int $count): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = $generator->generate($batch->code_length, $batch->charset, $batch->prefix);
            $codes[$code] = $code;
        }

        $taken = Voucher::where('router_id', $batch->router_id)->whereIn('code', array_keys($codes))->pluck('code')->all();

        return array_values(array_diff($codes, $taken));
    }
}
