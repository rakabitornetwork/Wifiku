<?php

namespace App\Jobs\Network;

use App\Models\Customer;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Membuat atau memperbarui PPP secret pelanggan pada router terkait.
 * Bila pelanggan pindah router, secret di router lama dihapus lebih dulu.
 */
class SyncPppoeSecretJob implements ShouldQueue
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
    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Customer $customer,
        public ?string $previousUsername = null,
        public ?int $previousRouterId = null,
    ) {}

    /**
     * Satu router hanya menerima satu perintah API pada satu waktu.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('router:'.$this->customer->router_id))->releaseAfter(10)->expireAfter(45)];
    }

    /**
     * Batas waktu job tetap dicoba ulang selama router sibuk.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $customer = $this->customer->loadMissing(['router', 'package']);

        // Pelanggan yang dihapus tidak boleh dibuat ulang di router oleh sinkronisasi yang masih mengantre.
        if ($customer->trashed()) {
            return;
        }

        if ($this->previousRouterId !== null && $this->previousRouterId !== $customer->router_id) {
            $previousRouter = Router::withTrashed()->find($this->previousRouterId);

            if ($previousRouter !== null) {
                MikrotikService::forRouter($previousRouter)->removePppoeSecret($this->previousUsername ?? $customer->pppoe_username);
            }
        }

        MikrotikService::forRouter($customer->router)->syncPppoeSecret($customer, $this->previousUsername);

        $customer->forceFill(['router_synced_at' => now()])->saveQuietly();
    }
}
