<?php

namespace App\Jobs\Voucher;

use App\Enums\VoucherBatchStatus;
use App\Enums\VoucherStatus;
use App\Models\Router;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Mikrotik\MikrotikService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Menghapus voucher dari router (hotspot user beserta sesi aktifnya) lalu dari database, per satu batch atau seluruh router.
 * Dikerjakan per potongan: baris database baru dihapus setelah router berhasil dibersihkan, sehingga aman diulang
 * bila router sempat tidak terjangkau. Batch yang menjadi kosong ikut dihapus.
 */
class DeleteVouchersJob implements ShouldQueue
{
    use Queueable;

    private const CHUNK = 200;

    /**
     * Jumlah exception yang diizinkan sebelum job gagal.
     * Release karena antre kunci router tidak dihitung sebagai kegagalan.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * @param  int|null  $batchId  Batch tertentu, atau null untuk seluruh voucher router.
     * @param  bool  $includeUsed  Ikut menghapus voucher yang sudah dipakai (aktif/kedaluwarsa), bukan hanya yang belum terpakai.
     */
    public function __construct(public int $routerId, public ?int $batchId = null, public bool $includeUsed = false) {}

    /**
     * Satu router hanya menerima satu perintah API pada satu waktu.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('router:'.$this->routerId))->releaseAfter(10)->expireAfter(315)];
    }

    /**
     * Batas waktu job tetap dicoba ulang selama router sibuk atau belum terjangkau.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHours(2);
    }

    public function handle(): void
    {
        $vouchers = $this->targets()->orderBy('id')->limit(self::CHUNK)->get(['id', 'code']);

        if ($vouchers->isNotEmpty()) {
            $router = Router::withTrashed()->find($this->routerId);

            if ($router !== null) {
                $service = MikrotikService::forRouter($router);

                foreach ($vouchers as $voucher) {
                    $service->removeHotspotUser($voucher->code);
                }
            }

            Voucher::whereKey($vouchers->modelKeys())->delete();
        }

        if ($vouchers->count() === self::CHUNK) {
            self::dispatch($this->routerId, $this->batchId, $this->includeUsed);

            return;
        }

        $this->deleteEmptyBatches();
    }

    /**
     * Voucher sasaran. Batch yang masih digenerate tidak disentuh.
     *
     * @return Builder<Voucher>
     */
    private function targets(): Builder
    {
        return Voucher::query()
            ->where('router_id', $this->routerId)
            ->when($this->batchId, fn ($query) => $query->where('voucher_batch_id', $this->batchId))
            ->when(! $this->includeUsed, fn ($query) => $query->whereIn('status', [VoucherStatus::Unused, VoucherStatus::Disabled]))
            ->whereHas('voucherBatch', fn ($query) => $query->whereNotIn('status', [VoucherBatchStatus::Queued, VoucherBatchStatus::Processing]));
    }

    private function deleteEmptyBatches(): void
    {
        VoucherBatch::query()
            ->where('router_id', $this->routerId)
            ->when($this->batchId, fn ($query) => $query->whereKey($this->batchId))
            ->whereNotIn('status', [VoucherBatchStatus::Queued, VoucherBatchStatus::Processing])
            ->whereDoesntHave('vouchers')
            ->delete();
    }
}
