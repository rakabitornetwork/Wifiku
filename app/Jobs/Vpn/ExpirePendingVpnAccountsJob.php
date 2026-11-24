<?php

namespace App\Jobs\Vpn;

use App\Enums\PaymentStatus;
use App\Enums\VpnAccountStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\VpnAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus akun VPN buatan member yang tidak dibayar dalam 3 hari agar kuota dan IP-nya kembali bebas.
 * Akun yang sudah punya pembayaran lunas tidak pernah dihapus.
 */
class ExpirePendingVpnAccountsJob implements ShouldQueue
{
    use Queueable;

    private const GRACE_DAYS = 3;

    public function handle(): void
    {
        VpnAccount::query()
            ->where('status', VpnAccountStatus::Pending)
            ->where('created_at', '<', now()->subDays(self::GRACE_DAYS))
            ->each(function (VpnAccount $account): void {
                DB::transaction(function () use ($account): void {
                    $invoiceIds = Invoice::withTrashed()->where('vpn_account_id', $account->id)->pluck('id');

                    if (Payment::query()->whereIn('invoice_id', $invoiceIds)->where('status', PaymentStatus::Paid)->exists()) {
                        return;
                    }

                    Payment::query()->whereIn('invoice_id', $invoiceIds)->delete();
                    Invoice::withTrashed()->whereIn('id', $invoiceIds)->forceDelete();
                    $account->delete();
                });
            });
    }
}
