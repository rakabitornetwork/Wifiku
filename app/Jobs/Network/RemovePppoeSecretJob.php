<?php

namespace App\Jobs\Network;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Menghapus PPP secret (dan sesi aktifnya) dari router saat pelanggan dihapus.
 * Memakai ID router dan username agar tetap berjalan walau data pelanggan sudah diarsipkan.
 */
class RemovePppoeSecretJob implements ShouldQueue
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

    public function __construct(public int $routerId, public string $username) {}

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
     * Batas waktu job tetap dicoba ulang selama router sibuk atau belum terjangkau.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    public function handle(): void
    {
        $router = Router::withTrashed()->find($this->routerId);

        if ($router !== null) {
            MikrotikService::forRouter($router)->removePppoeSecret($this->username);
        }
    }
}
