<?php

namespace App\Jobs\Voucher;

use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Mikrotik\MikrotikService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;

/**
 * Mengirim voucher batch yang belum tersinkron ke hotspot user pada router terkait.
 * Aman diulang: user yang sudah ada di router (komentar vc-{batch}) dilewati, dan progres disimpan per chunk.
 */
class PushVouchersToRouterJob implements ShouldQueue
{
    use Queueable;

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
     * Create a new job instance.
     */
    public function __construct(public VoucherBatch $voucherBatch) {}

    /**
     * Satu router hanya menerima satu perintah API pada satu waktu.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('router:'.$this->voucherBatch->router_id))->releaseAfter(10)->expireAfter(315)];
    }

    /**
     * Batas waktu job tetap dicoba ulang selama router sibuk.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHours(6);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batch = $this->voucherBatch->loadMissing(['router', 'hotspotProfile']);
        $comment = 'vc-'.$batch->batch_code;
        $router = MikrotikService::forRouter($batch->router);
        $existing = array_flip($router->hotspotUserNames($comment));

        $batch->vouchers()
            ->where('synced_to_router', false)
            ->chunkById(100, function (Collection $vouchers) use ($router, $batch, $comment, $existing): void {
                foreach ($vouchers as $voucher) {
                    if (! isset($existing[$voucher->code])) {
                        $router->createHotspotUser($voucher, $batch->hotspotProfile, $comment, $batch->server);
                    }
                }

                Voucher::whereKey($vouchers->modelKeys())->update(['synced_to_router' => true]);
            });
    }
}
