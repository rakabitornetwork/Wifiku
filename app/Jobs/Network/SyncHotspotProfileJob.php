<?php

namespace App\Jobs\Network;

use App\Models\HotspotProfile;
use App\Services\Mikrotik\MikrotikService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Membuat atau memperbarui Hotspot User Profile di router agar sama dengan profil aplikasi.
 */
class SyncHotspotProfileJob implements ShouldQueue
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
    public function __construct(public HotspotProfile $profile, public ?string $previousName = null) {}

    /**
     * Satu router hanya menerima satu perintah API pada satu waktu.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('router:'.$this->profile->router_id))->releaseAfter(10)->expireAfter(45)];
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
        $profile = $this->profile->loadMissing('router');

        // Profil yang sudah dihapus di aplikasi tidak boleh dibuat ulang oleh sinkronisasi yang masih mengantre.
        if (! $profile->exists || ! HotspotProfile::query()->whereKey($profile->id)->exists()) {
            return;
        }

        MikrotikService::forRouter($profile->router)->syncHotspotProfile($profile, $this->previousName);
    }
}
