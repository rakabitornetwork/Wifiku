<?php

namespace App\Services\Vpn;

use App\Models\VpnServer;

/**
 * Memilih IP tunnel berikutnya yang kosong dari pool sebuah server VPN.
 */
class VpnAddressAllocator
{
    /**
     * IP bebas pertama di pool, atau null bila pool penuh.
     */
    public function next(VpnServer $server, ?int $ignoreAccountId = null): ?string
    {
        $used = $server->accounts()
            ->when($ignoreAccountId, fn ($query) => $query->whereKeyNot($ignoreAccountId))
            ->pluck('remote_address')
            ->map(fn (string $address): int => (int) ip2long($address))
            ->flip();

        $local = (int) ip2long($server->local_address);

        for ($address = (int) ip2long($server->pool_start); $address <= (int) ip2long($server->pool_end); $address++) {
            if ($address !== $local && ! $used->has($address)) {
                return long2ip($address);
            }
        }

        return null;
    }

    /**
     * Apakah $address berada di dalam pool server (dan bukan IP lokal server).
     */
    public function inPool(VpnServer $server, string $address): bool
    {
        $long = ip2long($address);

        return $long !== false
            && $long >= ip2long($server->pool_start)
            && $long <= ip2long($server->pool_end)
            && $long !== ip2long($server->local_address);
    }
}
