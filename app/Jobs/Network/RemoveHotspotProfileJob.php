<?php

namespace App\Jobs\Network;

use App\Models\HotspotProfile;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Menghapus Hotspot User Profile dari router saat profil dihapus di aplikasi.
 * Memakai ID router dan nama profile agar tetap berjalan walau datanya sudah terhapus.
 * Profile yang masih dipakai hotspot user atau profil aplikasi lain tidak dihapus.
 */
class RemoveHotspotProfileJob implements ShouldQueue
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

    public function __construct(public int $routerId, public string $profileName) {}

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

        if ($router === null) {
            return;
        }

        $stillUsed = HotspotProfile::query()->where('router_id', $this->routerId)->where('mikrotik_profile', $this->profileName)->exists();

        if ($stillUsed) {
            return;
        }

        $result = MikrotikService::forRouter($router)->removeHotspotProfile($this->profileName);

        if ($result === 'in_use') {
            Log::warning('Profile hotspot tidak dihapus dari router karena masih dipakai hotspot user.', ['router_id' => $this->routerId, 'profile' => $this->profileName]);
        }
    }
}
