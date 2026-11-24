<?php

namespace App\Jobs\Vpn;

use App\Models\VpnServer;
use App\Services\Vpn\VpnProvisioner;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Menghapus PPP secret, sesi aktif, dan port forward akun VPN yang sudah dihapus dari aplikasi.
 */
class RemoveVpnAccountJob implements ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $vpnServerId, public int $routerId, public string $username) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('router:'.$this->routerId))->releaseAfter(10)->expireAfter(45)];
    }

    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    public function handle(VpnProvisioner $provisioner): void
    {
        $server = VpnServer::query()->with('router')->find($this->vpnServerId);

        if ($server === null) {
            return;
        }

        $provisioner->removeAccount($server, $this->username);
    }
}
