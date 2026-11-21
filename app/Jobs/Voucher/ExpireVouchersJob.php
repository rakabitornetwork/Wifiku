<?php

namespace App\Jobs\Voucher;

use App\Enums\VoucherStatus;
use App\Models\Router;
use App\Models\Voucher;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Menghapus voucher yang masa aktifnya habis dari router lalu menandainya kedaluwarsa.
 * Router yang tidak terjangkau dilewati; vouchernya dicoba lagi pada jadwal berikutnya.
 */
class ExpireVouchersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const PER_RUN = 500;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 240;

    public int $uniqueFor = 300;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $expired = Voucher::query()
            ->where('status', VoucherStatus::Active)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(self::PER_RUN)
            ->get(['id', 'router_id', 'code'])
            ->groupBy('router_id');

        foreach ($expired as $routerId => $vouchers) {
            $router = Router::withTrashed()->find($routerId);

            if ($router === null) {
                continue;
            }

            $service = MikrotikService::forRouter($router);
            $removed = [];

            try {
                foreach ($vouchers as $voucher) {
                    $service->removeHotspotUser($voucher->code);
                    $removed[] = $voucher->id;
                }
            } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
                Log::warning('Gagal menghapus voucher kedaluwarsa dari router.', ['router_id' => $routerId, 'error' => $exception->getMessage()]);
            }

            Voucher::whereKey($removed)->update(['status' => VoucherStatus::Expired]);
        }
    }
}
