<?php

namespace App\Actions\Asset;

use App\Enums\AssetOwnership;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Services\GenieAcs\GenieAcsService;

/**
 * Impor perangkat dari GenieACS ke inventaris: ONT baru dibuat sebagai Stok, ONT yang sudah ada
 * (cocok lewat device ID atau serial number) hanya dilengkapi data TR-069-nya.
 */
class ImportGenieAcsDevices
{
    public function __construct(private readonly GenieAcsService $genieAcs) {}

    /**
     * @return array{created: int, updated: int}
     */
    public function handle(): array
    {
        $devices = $this->genieAcs->listDevices();
        $created = 0;
        $updated = 0;

        foreach ($devices as $device) {
            $asset = Asset::query()
                ->where('genieacs_device_id', $device['device_id'])
                ->orWhere('serial_number', $device['serial_number'])
                ->first();

            $telemetry = array_filter([
                'last_rx_power' => $device['rx_power'],
                'last_seen_at' => $device['last_inform'],
            ], fn ($value) => $value !== null);

            if ($asset === null) {
                Asset::create([
                    'type' => AssetType::Ont,
                    'brand' => $device['manufacturer'],
                    'model' => $device['model'],
                    'serial_number' => $device['serial_number'],
                    'ownership' => AssetOwnership::Company,
                    'status' => AssetStatus::InStock,
                    'genieacs_device_id' => $device['device_id'],
                    ...$telemetry,
                ]);
                $created++;

                continue;
            }

            $asset->fill([
                'genieacs_device_id' => $device['device_id'],
                'brand' => $asset->brand ?: $device['manufacturer'],
                'model' => $asset->model ?: $device['model'],
                ...$telemetry,
            ]);

            if ($asset->isDirty()) {
                $asset->save();
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
