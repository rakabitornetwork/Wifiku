<?php

namespace App\Services\Portal;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Customer;
use App\Services\GenieAcs\GenieAcsException;
use App\Services\GenieAcs\GenieAcsService;

/**
 * Data ONU untuk portal pelanggan: perangkat milik pelanggan itu sendiri, dibaca langsung dari GenieACS.
 * Password Wi-Fi sengaja tidak pernah dikirim ke browser.
 */
class CustomerOnuService
{
    /** ONT dianggap online bila terakhir melapor ke GenieACS dalam rentang ini. */
    public const ONLINE_WINDOW_MINUTES = 30;

    public function __construct(private readonly GenieAcsService $genieAcs) {}

    public function assetFor(Customer $customer): ?Asset
    {
        return $customer->assets()
            ->where('type', AssetType::Ont)
            ->where('status', AssetStatus::Installed)
            ->orderBy('id')
            ->first();
    }

    public function canChangeWifi(Customer $customer): bool
    {
        return $this->genieAcs->isConfigured() && $this->assetFor($customer) !== null;
    }

    /**
     * @return array{available: bool, message: string|null, online: bool|null, rx_power: float|null, rx_quality: 'good'|'warning'|'bad'|null, last_inform: string|null, ssid: string|null, devices: list<array{hostname: string|null, ip_address: string|null, mac_address: string|null, interface: string|null}>}
     */
    public function live(Customer $customer): array
    {
        $empty = ['available' => false, 'message' => null, 'online' => null, 'rx_power' => null, 'rx_quality' => null, 'last_inform' => null, 'ssid' => null, 'devices' => []];
        $asset = $this->assetFor($customer);

        if ($asset === null) {
            return [...$empty, 'message' => 'Belum ada ONU yang tercatat untuk akun Anda. Hubungi CS bila ini keliru.'];
        }

        if (! $this->genieAcs->isConfigured()) {
            return [...$empty, 'message' => 'Pemantauan ONU belum diaktifkan.'];
        }

        try {
            $status = $this->genieAcs->readStatus($asset);
            $wifi = $this->genieAcs->readWifiDetails($asset);
        } catch (GenieAcsException) {
            return [...$empty, 'message' => 'Status ONU belum dapat dibaca saat ini. Coba lagi beberapa saat lagi.'];
        }

        $lastInform = $wifi['last_inform'] ?? $status['last_inform'];

        return [
            'available' => true,
            'message' => null,
            'online' => $lastInform !== null && $lastInform->greaterThan(now()->subMinutes(self::ONLINE_WINDOW_MINUTES)),
            'rx_power' => $status['rx_power'],
            'rx_quality' => self::quality($status['rx_power']),
            'last_inform' => $lastInform?->toIso8601String(),
            'ssid' => $wifi['ssid'],
            'devices' => $wifi['devices'],
        ];
    }

    /**
     * Redaman optik yang dapat diterima GPON kira-kira -8 sampai -27 dBm; di luar itu sinyal terlalu kuat atau lemah.
     *
     * @return 'good'|'warning'|'bad'|null
     */
    public static function quality(?float $rxPower): ?string
    {
        return match (true) {
            $rxPower === null => null,
            $rxPower > -8 => 'warning',
            $rxPower >= -25 => 'good',
            $rxPower >= -28 => 'warning',
            default => 'bad',
        };
    }
}
