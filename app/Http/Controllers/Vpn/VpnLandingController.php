<?php

namespace App\Http\Controllers\Vpn;

use App\Http\Controllers\Controller;
use App\Models\VpnServer;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page publik layanan VPN Tunnel: penjelasan, harga, dan tombol daftar.
 */
class VpnLandingController extends Controller
{
    public function __invoke(SettingService $settings): Response
    {
        $servers = VpnServer::query()->where('is_active', true)->get(['l2tp_enabled', 'sstp_enabled']);
        $csNumber = preg_replace('/\D+/', '', (string) $settings->get('company', 'cs_whatsapp'));

        return Inertia::render('Public/Vpn/Landing', [
            'company' => $settings->get('company', 'name', config('app.name')),
            'price' => (int) $settings->get('vpn', 'member_price'),
            'maxAccounts' => (int) $settings->get('vpn', 'member_max_accounts'),
            'registrationOpen' => $settings->get('vpn', 'member_registration_open') === '1',
            'protocols' => array_values(array_filter([
                $servers->contains('l2tp_enabled', true) ? 'L2TP' : null,
                $servers->contains('sstp_enabled', true) ? 'SSTP' : null,
            ])),
            'loggedIn' => Auth::guard('member')->check(),
            'whatsappUrl' => $csNumber !== '' ? 'https://wa.me/'.$csNumber.'?text='.rawurlencode('Halo, saya ingin bertanya tentang layanan VPN Tunnel.') : null,
        ]);
    }
}
