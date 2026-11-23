<?php

namespace App\Jobs\Network;

use App\Models\Package;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Membuat atau memperbarui PPP Profile di router agar sama dengan paket aplikasi.
 */
class SyncPppProfileJob implements ShouldQueue
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
    public function __construct(public Package $package, public int $routerId, public ?string $previousName = null) {}

    /**
     * Satu router hanya menerima satu perintah API pada satu waktu.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('router:'.$this->routerId))->releaseAfter(10)->expireAfter(45)];
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
        $router = Router::find($this->routerId);

        // Paket yang sudah dihapus di aplikasi tidak boleh dibuat ulang oleh sinkronisasi yang masih mengantre.
        $package = Package::query()->find($this->package->id);

        if ($router === null || $package === null) {
            return;
        }

        MikrotikService::forRouter($router)->syncPppProfile($package, $this->previousName);
    }
}
