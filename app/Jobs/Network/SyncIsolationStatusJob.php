<?php

namespace App\Jobs\Network;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Menyamakan status pelanggan dengan profile PPP di router, dua arah:
 * profile isolir → Terisolir, dan profile normal → Aktif (untuk pelanggan yang diisolir/dibuka langsung di router).
 * Tidak mengirim WhatsApp karena perubahannya bukan dari aplikasi ini. Pelanggan yang baru saja diubah
 * dilewati agar perubahan dari form yang masih mengantre ke router tidak dibatalkan.
 */
class SyncIsolationStatusJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Berjalan berkala, jadi tidak perlu dicoba ulang saat router tidak terjangkau.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Cegah antrean menumpuk bila worker tertinggal dari jadwal.
     */
    public int $uniqueFor = 300;

    /**
     * Menit sejak perubahan terakhir sebelum status pelanggan boleh disamakan dengan router.
     */
    public const SETTLE_MINUTES = 10;

    public function __construct(public Router $router) {}

    public function uniqueId(): string
    {
        return (string) $this->router->id;
    }

    /**
     * Satu router hanya menerima satu perintah API pada satu waktu.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('router:'.$this->router->id))->releaseAfter(10)->expireAfter(45)];
    }

    public function handle(): void
    {
        $profiles = MikrotikService::forRouter($this->router)->secretProfiles();

        Customer::query()
            ->where('router_id', $this->router->id)
            ->whereIn('status', [CustomerStatus::Active, CustomerStatus::Isolated])
            ->where('updated_at', '<=', now()->subMinutes(self::SETTLE_MINUTES))
            ->chunkById(200, function ($customers) use ($profiles): void {
                foreach ($customers as $customer) {
                    if (! isset($profiles[$customer->pppoe_username])) {
                        continue;
                    }

                    $inIsolirProfile = $this->router->isIsolirProfile($profiles[$customer->pppoe_username]);

                    if ($inIsolirProfile && $customer->status === CustomerStatus::Active) {
                        $customer->update(['status' => CustomerStatus::Isolated, 'isolated_at' => now()]);
                    } elseif (! $inIsolirProfile && $customer->status === CustomerStatus::Isolated) {
                        $customer->update(['status' => CustomerStatus::Active, 'isolated_at' => null]);
                    }
                }
            });
    }
}
