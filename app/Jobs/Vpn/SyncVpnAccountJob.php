<?php

namespace App\Jobs\Vpn;

use App\Enums\VpnAccountStatus;
use App\Models\VpnAccount;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Vpn\VpnProvisioner;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Memasang atau memperbarui akun VPN (PPP secret + port forward) di router server.
 */
class SyncVpnAccountJob implements ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $accountId, public int $routerId, public ?string $previousUsername = null) {}

    /**
     * Satu router hanya menerima satu perintah API pada satu waktu.
     *
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
        // Akun yang sudah dihapus tidak boleh dibuat ulang oleh sinkronisasi yang masih mengantre.
        $account = VpnAccount::query()->with('server.router')->find($this->accountId);

        // Akun yang belum dibayar belum boleh dipasang di router.
        if ($account === null || $account->status === VpnAccountStatus::Pending) {
            return;
        }

        try {
            $provisioner->syncAccount($account, $this->previousUsername);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            $account->forceFill(['last_error' => $exception->getMessage()])->save();

            throw $exception;
        }
    }
}
