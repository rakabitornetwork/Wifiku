<?php

namespace App\Jobs\Vpn;

use App\Enums\VpnAccountStatus;
use App\Models\VpnAccount;
use App\Services\Whatsapp\VpnNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Menonaktifkan akun VPN yang sudah lewat masa aktif dan memutus tunnel-nya di router server.
 */
class ExpireVpnAccountsJob implements ShouldQueue
{
    use Queueable;

    public function handle(VpnNotifier $notifier): void
    {
        VpnAccount::query()
            ->with('server:id,router_id')
            ->where('status', VpnAccountStatus::Active)
            ->whereDate('expires_at', '<', now()->toDateString())
            ->each(function (VpnAccount $account) use ($notifier): void {
                $account->update(['status' => VpnAccountStatus::Disabled, 'auto_disabled_at' => now()]);

                SyncVpnAccountJob::dispatch($account->id, $account->server->router_id);
                $notifier->suspended($account);
            });
    }
}
