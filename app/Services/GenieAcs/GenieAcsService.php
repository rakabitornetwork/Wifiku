<?php

namespace App\Services\GenieAcs;

use App\Models\Asset;
use App\Services\Settings\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Integrasi TR-069 via NBI API GenieACS: membaca redaman optik (RX Power) dan mengubah SSID/password Wi-Fi ONT.
 * Nama parameter berbeda per vendor, sehingga jalurnya diatur di Pengaturan Integrasi.
 */
class GenieAcsService
{
    private const PAGE_SIZE = 200;

    private const MAX_IMPORT_DEVICES = 2000;

    public function __construct(private readonly SettingService $settings) {}

    public function isConfigured(): bool
    {
        return filled($this->settings->get('genieacs', 'base_url'));
    }

    /**
     * ID perangkat GenieACS milik aset; dicari lewat serial number bila belum tersimpan.
     */
    public function deviceId(Asset $asset): string
    {
        if (filled($asset->genieacs_device_id)) {
            return $asset->genieacs_device_id;
        }

        $devices = $this->get('/devices', [
            'query' => json_encode(['_deviceId._SerialNumber' => $asset->serial_number]),
            'projection' => '_id',
        ])->json();

        $id = $devices[0]['_id'] ?? throw new GenieAcsException("Perangkat {$asset->serial_number} tidak ditemukan di GenieACS.");

        $asset->forceFill(['genieacs_device_id' => $id])->save();

        return $id;
    }

    /**
     * Daftar perangkat terdaftar di GenieACS (maksimal MAX_IMPORT_DEVICES), dibaca per halaman.
     *
     * @return list<array{device_id: string, serial_number: string, manufacturer: string|null, model: string|null, rx_power: float|null, last_inform: CarbonImmutable|null}>
     */
    public function listDevices(): array
    {
        $paths = $this->rxPowerPaths();
        $devices = [];

        for ($skip = 0; $skip < self::MAX_IMPORT_DEVICES; $skip += self::PAGE_SIZE) {
            $page = $this->get('/devices', [
                'projection' => implode(',', ['_id', '_deviceId', '_lastInform', ...$paths]),
                'sort' => json_encode(['_id' => 1]),
                'limit' => (string) self::PAGE_SIZE,
                'skip' => (string) $skip,
            ])->json() ?? [];

            foreach ($page as $device) {
                $serial = data_get($device, '_deviceId._SerialNumber');

                if (! isset($device['_id']) || blank($serial) || $this->isIgnoredDevice($device)) {
                    continue;
                }

                $devices[] = [
                    'device_id' => $device['_id'],
                    'serial_number' => (string) $serial,
                    'manufacturer' => data_get($device, '_deviceId._Manufacturer') ?: null,
                    'model' => data_get($device, '_deviceId._ProductClass') ?: null,
                    'rx_power' => $this->extractRxPower($device, $paths),
                    'last_inform' => isset($device['_lastInform']) ? CarbonImmutable::parse($device['_lastInform']) : null,
                ];
            }

            if (count($page) < self::PAGE_SIZE) {
                break;
            }
        }

        return $devices;
    }

    /**
     * Entri bawaan GenieACS (penemuan perangkat) dan perangkat uji PoC, bukan ONT pelanggan.
     *
     * @param  array<string, mixed>  $device
     */
    private function isIgnoredDevice(array $device): bool
    {
        $manufacturer = strtoupper((string) data_get($device, '_deviceId._Manufacturer'));
        $productClass = strtoupper((string) data_get($device, '_deviceId._ProductClass'));

        return in_array('DISCOVERYSERVICE', [$manufacturer, $productClass], true)
            || ($manufacturer === 'POC' && $productClass === 'POCLAB');
    }

    /**
     * @return list<string>
     */
    private function rxPowerPaths(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->settings->get('genieacs', 'rx_power_paths')))));
    }

    /**
     * @param  array<string, mixed>  $device
     * @param  list<string>  $paths
     */
    private function extractRxPower(array $device, array $paths): ?float
    {
        foreach ($paths as $path) {
            $value = data_get($device, $path.'._value');

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * SSID, password Wi-Fi, dan perangkat yang sedang terhubung, sesuai data terakhir yang diketahui GenieACS.
     * Password hanya dibaca untuk ditampilkan, tidak disimpan di database aplikasi.
     *
     * @return array{ssid: string|null, password: string|null, last_inform: CarbonImmutable|null, devices: list<array{hostname: string|null, ip_address: string|null, mac_address: string|null, interface: string|null}>}
     */
    public function readWifiDetails(Asset $asset): array
    {
        $ssidPath = (string) $this->settings->get('genieacs', 'ssid_path');
        $passwordPath = (string) $this->settings->get('genieacs', 'wifi_password_path');
        $hostsPath = trim((string) $this->settings->get('genieacs', 'hosts_path'));

        $device = $this->get('/devices', [
            'query' => json_encode(['_id' => $this->deviceId($asset)]),
            'projection' => implode(',', array_filter([$ssidPath, $passwordPath, $hostsPath, '_lastInform'])),
        ])->json()[0] ?? throw new GenieAcsException("Perangkat {$asset->serial_number} tidak ditemukan di GenieACS.");

        $devices = [];

        foreach ((array) data_get($device, $hostsPath, []) as $key => $host) {
            if (str_starts_with((string) $key, '_') || ! is_array($host)) {
                continue;
            }

            $active = data_get($host, 'Active._value');

            if ($active !== null && ! filter_var($active, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $devices[] = [
                'hostname' => $this->stringValue($host, 'HostName'),
                'ip_address' => $this->stringValue($host, 'IPAddress'),
                'mac_address' => $this->stringValue($host, 'MACAddress', 'PhysAddress'),
                'interface' => $this->stringValue($host, 'InterfaceType', 'Layer1Interface'),
            ];
        }

        return [
            'ssid' => $this->stringValue($device, $ssidPath),
            'password' => $this->stringValue($device, $passwordPath),
            'last_inform' => isset($device['_lastInform']) ? CarbonImmutable::parse($device['_lastInform']) : null,
            'devices' => $devices,
        ];
    }

    /**
     * Nilai parameter TR-069 pertama yang terisi, atau null.
     *
     * @param  array<string, mixed>  $source
     */
    private function stringValue(array $source, string ...$paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($source, $path.'._value');

            if (is_scalar($value) && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * RX Power (dBm) dan waktu inform terakhir ONT.
     *
     * @return array{rx_power: float|null, last_inform: CarbonImmutable|null}
     */
    public function readStatus(Asset $asset): array
    {
        $paths = $this->rxPowerPaths();

        $device = $this->get('/devices', [
            'query' => json_encode(['_id' => $this->deviceId($asset)]),
            'projection' => implode(',', [...$paths, '_lastInform']),
        ])->json()[0] ?? throw new GenieAcsException("Perangkat {$asset->serial_number} tidak ditemukan di GenieACS.");

        return [
            'rx_power' => $this->extractRxPower($device, $paths),
            'last_inform' => isset($device['_lastInform']) ? CarbonImmutable::parse($device['_lastInform']) : null,
        ];
    }

    /**
     * RX Power ONT dalam dBm, atau null bila parameter tidak dilaporkan perangkat.
     */
    public function getRxPower(Asset $asset): ?float
    {
        return $this->readStatus($asset)['rx_power'];
    }

    /**
     * Ubah SSID dan password Wi-Fi. Mengembalikan true bila langsung diterapkan,
     * false bila perangkat sedang offline dan tugas diantrekan GenieACS sampai ONT inform berikutnya.
     */
    public function updateWifi(Asset $asset, string $ssid, string $password): bool
    {
        $response = $this->send(fn (PendingRequest $http) => $http
            ->withQueryParameters(['connection_request' => '', 'timeout' => 3000])
            ->post('/devices/'.rawurlencode($this->deviceId($asset)).'/tasks', [
                'name' => 'setParameterValues',
                'parameterValues' => [
                    [$this->settings->get('genieacs', 'ssid_path'), $ssid, 'xsd:string'],
                    [$this->settings->get('genieacs', 'wifi_password_path'), $password, 'xsd:string'],
                ],
            ]));

        return $response->status() === 200;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function get(string $path, array $query): Response
    {
        return $this->send(fn (PendingRequest $http) => $http->get($path, $query));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request): Response
    {
        $baseUrl = $this->settings->get('genieacs', 'base_url') ?? throw new GenieAcsException('GenieACS belum dikonfigurasi.');

        $http = Http::baseUrl($baseUrl)->acceptJson()->connectTimeout(5)->timeout(10);

        if (filled($username = $this->settings->get('genieacs', 'username'))) {
            $http = $http->withBasicAuth($username, (string) $this->settings->get('genieacs', 'password'));
        }

        try {
            $response = $request($http);
        } catch (ConnectionException $exception) {
            throw new GenieAcsException("Tidak dapat menghubungi GenieACS: {$exception->getMessage()}", previous: $exception);
        }

        if ($response->failed()) {
            throw new GenieAcsException("GenieACS menolak permintaan (HTTP {$response->status()}): ".str($response->body())->limit(200));
        }

        return $response;
    }
}
