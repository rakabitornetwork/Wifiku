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
     * RX Power (dBm) dan waktu inform terakhir ONT.
     *
     * @return array{rx_power: float|null, last_inform: CarbonImmutable|null}
     */
    public function readStatus(Asset $asset): array
    {
        $paths = array_values(array_filter(array_map('trim', explode(',', (string) $this->settings->get('genieacs', 'rx_power_paths')))));

        $device = $this->get('/devices', [
            'query' => json_encode(['_id' => $this->deviceId($asset)]),
            'projection' => implode(',', [...$paths, '_lastInform']),
        ])->json()[0] ?? throw new GenieAcsException("Perangkat {$asset->serial_number} tidak ditemukan di GenieACS.");

        $rxPower = null;

        foreach ($paths as $path) {
            $value = data_get($device, $path.'._value');

            if (is_numeric($value)) {
                $rxPower = (float) $value;

                break;
            }
        }

        return [
            'rx_power' => $rxPower,
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
