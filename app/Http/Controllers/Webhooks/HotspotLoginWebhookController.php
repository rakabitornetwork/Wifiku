<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotspotLoginWebhookController extends Controller
{
    /**
     * Dipanggil script on-login Hotspot User Profile MikroTik.
     * Login pertama voucher mengaktifkannya dan memulai hitungan masa aktif; login berikutnya diabaikan.
     */
    public function __invoke(Request $request, Router $router): JsonResponse
    {
        $token = (string) $request->input('token');

        if (blank($router->webhook_token) || ! hash_equals($router->webhook_token, $token)) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 403);
        }

        $voucher = Voucher::with('hotspotProfile:id,validity_minutes')
            ->where('router_id', $router->id)
            ->where('code', (string) $request->input('user'))
            ->first();

        if ($voucher === null) {
            return response()->json(['success' => true, 'message' => 'Not a voucher user']);
        }

        // Pembaruan bersyarat agar dua login nyaris bersamaan tidak memundurkan masa aktif.
        $activated = Voucher::whereKey($voucher->id)->whereNull('first_login_at')->where('status', VoucherStatus::Unused)->update([
            'status' => VoucherStatus::Active,
            'first_login_at' => now(),
            'expires_at' => now()->addMinutes($voucher->hotspotProfile->validity_minutes),
            'mac_address' => substr((string) $request->input('mac'), 0, 17) ?: null,
        ]);

        return response()->json(['success' => true, 'activated' => $activated === 1]);
    }
}
