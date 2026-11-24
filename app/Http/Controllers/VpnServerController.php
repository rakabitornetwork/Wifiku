<?php

namespace App\Http\Controllers;

use App\Http\Requests\VpnServerRequest;
use App\Models\VpnServer;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Vpn\VpnProvisioner;
use App\Services\Vpn\VpnScriptBuilder;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class VpnServerController extends Controller
{
    public function store(VpnServerRequest $request): RedirectResponse
    {
        VpnServer::create($request->payload());

        Toast::success('Server VPN ditambahkan. Jalankan "Pasang ke router" atau salin script setup-nya.');

        return back();
    }

    public function update(VpnServerRequest $request, VpnServer $server): RedirectResponse
    {
        $server->update($request->payload());

        Toast::success('Server VPN diperbarui. Pasang ulang ke router agar perubahan berlaku.');

        return back();
    }

    public function destroy(VpnServer $server): RedirectResponse
    {
        if ($server->accounts()->exists()) {
            Toast::error('Server masih memiliki akun VPN. Hapus atau pindahkan akunnya lebih dulu.');

            return back();
        }

        $server->delete();

        Toast::success('Server VPN dihapus dari aplikasi. Konfigurasi di router tidak diubah.');

        return back();
    }

    /**
     * Script setup sekali jalan untuk router server (ditempel manual di terminal/Winbox).
     */
    public function script(VpnServer $server, VpnScriptBuilder $scripts): JsonResponse
    {
        return response()->json(['script' => $scripts->serverSetup($server->loadMissing('router'))]);
    }

    /**
     * Pasang setup server langsung ke router lewat API.
     */
    public function install(VpnServer $server, VpnProvisioner $provisioner): RedirectResponse
    {
        try {
            $provisioner->setupServer($server->load('router'));
            Toast::success("Setup VPN terpasang di router {$server->router->name}.");
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            Toast::error("Gagal memasang ke router: {$exception->getMessage()}");
        }

        return back();
    }
}
