<?php

namespace App\Http\Controllers;

use App\Models\VpnServer;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Vpn\VpnRouterImporter;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VpnImportController extends Controller
{
    /**
     * Akun di router server yang bisa diimpor (password tidak ikut dikirim ke browser).
     */
    public function preview(VpnServer $server, VpnRouterImporter $importer): JsonResponse
    {
        try {
            return response()->json(['rows' => $importer->preview($server->load('router'))]);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            return response()->json(['error' => "Gagal membaca router: {$exception->getMessage()}"], 422);
        }
    }

    public function store(Request $request, VpnServer $server, VpnRouterImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'usernames' => ['required', 'array', 'min:1', 'max:200'],
            'usernames.*' => ['string', 'max:255'],
            'monthly_price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'expires_at' => ['nullable', 'date'],
        ]);

        try {
            $count = $importer->import($server->load('router'), $validated['usernames'], (int) $validated['monthly_price'], $validated['expires_at'] ?? null);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            Toast::error("Gagal membaca router: {$exception->getMessage()}");

            return back();
        }

        Toast::success("{$count} akun VPN diimpor. Router tidak diubah; atur harga dan masa berlaku per akun bila perlu.");

        return back();
    }
}
