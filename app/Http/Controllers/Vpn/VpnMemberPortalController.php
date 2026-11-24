<?php

namespace App\Http\Controllers\Vpn;

use App\Actions\Vpn\CreateMemberVpnAccount;
use App\Enums\VpnAccountStatus;
use App\Enums\VpnProtocol;
use App\Http\Controllers\Controller;
use App\Http\Requests\VpnMemberAccountRequest;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\Settings\SettingService;
use App\Services\Vpn\VpnPortPlanner;
use App\Services\Vpn\VpnScriptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal pelanggan VPN Tunnel: melihat akun, membuat akun baru (maks. kuota), dan mengambil script RouterOS.
 */
class VpnMemberPortalController extends Controller
{
    public function index(Request $request, SettingService $settings): Response
    {
        $member = $request->user('member');
        $servers = VpnServer::query()->where('is_active', true)->get(['l2tp_enabled', 'sstp_enabled']);

        $accounts = $member->accounts()->with('server:id,public_host')->orderBy('id')->get()->map(fn (VpnAccount $account): array => [
            'id' => $account->id,
            'name' => $account->name,
            'protocol' => $account->protocol->label(),
            'username' => $account->username,
            'password' => $account->password,
            'host' => $account->server->public_host,
            'remote_address' => $account->remote_address,
            'forwards' => $account->forwards ?? [],
            'status' => $account->status->value,
            'expires_at' => $account->expires_at?->toDateString(),
            'monthly_price' => $account->monthly_price,
            'pay_url' => $account->status === VpnAccountStatus::Pending || $account->isExpired() ? route('vpn-portal.show', $account->portal_token) : null,
            'last_error' => $account->last_error !== null && $account->status === VpnAccountStatus::Active,
        ]);

        $max = (int) $settings->get('vpn', 'member_max_accounts');

        return Inertia::render('Public/Vpn/Dashboard', [
            'company' => $settings->get('company', 'name', config('app.name')),
            'member' => ['name' => $member->name, 'email' => $member->email],
            'accounts' => $accounts,
            'maxAccounts' => $max,
            'price' => (int) $settings->get('vpn', 'member_price'),
            'protocols' => array_values(array_filter([
                $servers->contains('sstp_enabled', true) ? 'sstp' : null,
                $servers->contains('l2tp_enabled', true) ? 'l2tp' : null,
            ])),
            'presets' => array_values(array_map(fn (array $preset): array => ['label' => $preset['label'], 'target' => $preset['target_port']], VpnPortPlanner::PRESETS)),
        ]);
    }

    public function store(VpnMemberAccountRequest $request, CreateMemberVpnAccount $createAccount): RedirectResponse
    {
        $validated = $request->validated();

        $account = $createAccount->handle(
            $request->user('member'),
            $validated['label'],
            VpnProtocol::from($validated['protocol']),
            array_map('intval', $validated['forwards'] ?? []),
        );

        // Langsung ke halaman bayar agar akun segera aktif.
        return redirect()->route('vpn-portal.show', $account->portal_token);
    }

    /**
     * Script RouterOS untuk router pelanggan; hanya untuk akun milik member yang sudah aktif.
     */
    public function script(Request $request, int $account, VpnScriptBuilder $scripts): JsonResponse
    {
        $vpnAccount = $request->user('member')->accounts()->with('server')->findOrFail($account);

        abort_unless($vpnAccount->status === VpnAccountStatus::Active, 403, 'Akun belum aktif.');

        return response()->json(['script' => $scripts->client($vpnAccount)]);
    }
}
