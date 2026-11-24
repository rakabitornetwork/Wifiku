<?php

namespace App\Http\Controllers;

use App\Http\Requests\VpnAccountRequest;
use App\Jobs\Vpn\RemoveVpnAccountJob;
use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\VpnAccount;
use App\Services\Vpn\VpnScriptBuilder;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class VpnAccountController extends Controller
{
    public function store(VpnAccountRequest $request): RedirectResponse
    {
        $account = VpnAccount::create($request->payload());

        SyncVpnAccountJob::dispatch($account->id, $account->server->router_id);

        Toast::success('Akun VPN dibuat; dipasang ke router server lewat antrean.');

        return back();
    }

    public function update(VpnAccountRequest $request, VpnAccount $account): RedirectResponse
    {
        $previousUsername = $account->username;

        $account->update($request->payload());

        SyncVpnAccountJob::dispatch($account->id, $account->server->router_id, $previousUsername);

        Toast::success('Akun VPN diperbarui; router server disinkronkan lewat antrean.');

        return back();
    }

    public function destroy(VpnAccount $account): RedirectResponse
    {
        $account->delete();

        RemoveVpnAccountJob::dispatch($account->vpn_server_id, $account->server->router_id, $account->username);

        Toast::success('Akun VPN dihapus; PPP secret dan port forward-nya dicabut dari router server lewat antrean.');

        return back();
    }

    /**
     * Pasang ulang akun ke router server (mis. setelah router server direset).
     */
    public function sync(VpnAccount $account): RedirectResponse
    {
        SyncVpnAccountJob::dispatch($account->id, $account->server->router_id);

        Toast::success('Sinkronisasi ke router server dijadwalkan.');

        return back();
    }

    /**
     * Script RouterOS untuk router pelanggan dan untuk router server. Berisi password akun.
     */
    public function scripts(VpnAccount $account, VpnScriptBuilder $scripts): JsonResponse
    {
        $account->load('server');

        return response()->json([
            'client' => $scripts->client($account),
            'server' => $scripts->serverAccount($account),
        ]);
    }
}
