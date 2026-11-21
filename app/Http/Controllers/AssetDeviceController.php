<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Services\GenieAcs\GenieAcsException;
use App\Services\GenieAcs\GenieAcsService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aksi TR-069 langsung pada satu ONT (satu perangkat, sehingga dijalankan sinkron dengan timeout singkat).
 */
class AssetDeviceController extends Controller
{
    public function rxPower(Asset $asset, GenieAcsService $genieAcs): RedirectResponse
    {
        $this->ensureOnt($asset);

        try {
            $status = $genieAcs->readStatus($asset);
        } catch (GenieAcsException $exception) {
            Toast::error($exception->getMessage());

            return back();
        }

        $asset->forceFill(['last_rx_power' => $status['rx_power'], 'last_seen_at' => $status['last_inform'] ?? $asset->last_seen_at])->save();

        Toast::success($status['rx_power'] === null
            ? 'Perangkat terhubung, tetapi parameter RX Power tidak dilaporkan. Periksa jalur parameter di Pengaturan.'
            : "RX Power {$asset->serial_number}: {$status['rx_power']} dBm.");

        return back();
    }

    public function wifi(Request $request, Asset $asset, GenieAcsService $genieAcs): RedirectResponse
    {
        $this->ensureOnt($asset);

        $validated = $request->validate([
            'ssid' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'max:63'],
        ]);

        try {
            $applied = $genieAcs->updateWifi($asset, $validated['ssid'], $validated['password']);
        } catch (GenieAcsException $exception) {
            Toast::error($exception->getMessage());

            return back();
        }

        Toast::success($applied
            ? 'SSID dan password Wi-Fi berhasil diubah.'
            : 'ONT sedang offline; perubahan Wi-Fi akan diterapkan saat ONT terhubung kembali.');

        return back();
    }

    private function ensureOnt(Asset $asset): void
    {
        abort_unless($asset->type === AssetType::Ont && $asset->status === AssetStatus::Installed, 422, 'Hanya ONT terpasang yang dapat dikelola via TR-069.');
    }
}
