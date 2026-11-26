<?php

namespace App\Http\Middleware;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pelindung halaman portal pelanggan: sesi harus berasal dari login OTP, belum lewat batas waktu mutlak,
 * dan pelanggannya masih ada dan tidak nonaktif. Pelanggan dimuat ulang tiap permintaan sehingga akses langsung berhenti
 * ketika akunnya dinonaktifkan atau dihapus.
 */
class EnsureCustomerPortalSession
{
    public const SESSION_KEY = 'portal.customer_id';

    public const LOGGED_IN_AT = 'portal.logged_in_at';

    public const MAX_HOURS = 12;

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $customer = null;

        if ($session->has(self::SESSION_KEY) && now()->timestamp - (int) $session->get(self::LOGGED_IN_AT) < self::MAX_HOURS * 3600) {
            $customer = Customer::query()->with('package:id,name,rate_limit')->find($session->get(self::SESSION_KEY));
        }

        if ($customer === null || $customer->status === CustomerStatus::Inactive) {
            $session->forget([self::SESSION_KEY, self::LOGGED_IN_AT]);

            return $request->expectsJson() ? response()->json(['message' => 'Sesi berakhir.'], 401) : redirect()->route('portal.login');
        }

        $request->attributes->set('portalCustomer', $customer);

        return $next($request);
    }
}
