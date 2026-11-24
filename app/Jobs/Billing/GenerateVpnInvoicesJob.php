<?php

namespace App\Jobs\Billing;

use App\Models\VpnAccount;
use App\Services\Vpn\VpnInvoiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Dijalankan harian: menerbitkan tagihan perpanjangan untuk akun VPN yang masa berlakunya hampir habis.
 */
class GenerateVpnInvoicesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(VpnInvoiceGenerator $generator): void
    {
        $today = CarbonImmutable::today();

        VpnAccount::query()
            ->whereNotNull('expires_at')
            ->where('monthly_price', '>', 0)
            ->chunkById(200, function (Collection $accounts) use ($generator, $today): void {
                foreach ($accounts as $account) {
                    $generator->generateFor($account, $today);
                }
            });
    }
}
