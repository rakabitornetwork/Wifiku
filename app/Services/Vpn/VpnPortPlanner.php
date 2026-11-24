<?php

namespace App\Services\Vpn;

use App\Models\VpnServer;

/**
 * Pola port forward server: port publik = (offset server + oktet terakhir IP tunnel) x 100 + kode layanan.
 * Contoh dengan offset 122: IP .16 → blok 138, sehingga Winbox 8291 → 13891 dan API 8728 → 13828.
 */
class VpnPortPlanner
{
    /**
     * Layanan standar: port tujuan di router pelanggan dan kode 2 digit di akhir port publik.
     *
     * @var array<string, array{label: string, target_port: int, suffix: int}>
     */
    public const PRESETS = [
        'ssh' => ['label' => 'SSH', 'target_port' => 22, 'suffix' => 22],
        'http' => ['label' => 'HTTP', 'target_port' => 80, 'suffix' => 80],
        'olt' => ['label' => 'OLT Hioso', 'target_port' => 8070, 'suffix' => 87],
        'winbox' => ['label' => 'Winbox', 'target_port' => 8291, 'suffix' => 91],
        'api' => ['label' => 'API RouterOS', 'target_port' => 8728, 'suffix' => 28],
    ];

    /**
     * Port publik untuk $targetPort pada IP tunnel $address; null bila port tujuan bukan layanan standar
     * atau hasilnya di luar rentang port yang bisa dipakai.
     */
    public function publicPort(VpnServer $server, string $address, int $targetPort): ?int
    {
        $suffix = collect(self::PRESETS)->firstWhere('target_port', $targetPort)['suffix'] ?? null;
        $octet = (int) substr($address, (int) strrpos($address, '.') + 1);

        if ($suffix === null || $octet < 1) {
            return null;
        }

        $port = ($server->port_block_offset + $octet) * 100 + $suffix;

        return $port >= 1024 && $port <= 65535 ? $port : null;
    }
}
