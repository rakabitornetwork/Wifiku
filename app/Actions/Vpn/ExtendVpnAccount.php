<?php

namespace App\Actions\Vpn;

use App\Enums\VpnAccountStatus;
use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\Invoice;
use App\Services\Whatsapp\VpnNotifier;

/**
 * Memperpanjang masa berlaku akun VPN setelah invoice-nya lunas dan membuka kembali akun
 * yang dimatikan otomatis karena kedaluwarsa (akun yang dinonaktifkan manual tidak disentuh).
 */
class ExtendVpnAccount
{
    public function __construct(private readonly VpnNotifier $notifier) {}

    public function handle(Invoice $invoice): void
    {
        $account = $invoice->vpnAccount;

        if ($account === null) {
            return;
        }

        $reopened = $account->status === VpnAccountStatus::Disabled && $account->auto_disabled_at !== null;

        $account->update([
            'expires_at' => $account->expires_at === null || $invoice->period_end->greaterThan($account->expires_at) ? $invoice->period_end : $account->expires_at,
            'status' => $reopened ? VpnAccountStatus::Active : $account->status,
            'auto_disabled_at' => $reopened ? null : $account->auto_disabled_at,
        ]);

        SyncVpnAccountJob::dispatch($account->id, $account->server->router_id);

        $this->notifier->extended($account->refresh(), $reopened);
    }
}
