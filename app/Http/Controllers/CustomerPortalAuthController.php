<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCustomerPortalSession;
use App\Services\Portal\CustomerLoginService;
use App\Services\Settings\SettingService;
use App\Support\CompanyBranding;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login portal pelanggan PPPoE: kode pelanggan lalu kode OTP dari WhatsApp. Terpisah dari login admin dan member VPN.
 */
class CustomerPortalAuthController extends Controller
{
    private const PENDING_KEY = 'portal.pending_code';

    public function showLogin(Request $request, SettingService $settings): Response|RedirectResponse
    {
        if ($request->session()->has(EnsureCustomerPortalSession::SESSION_KEY)) {
            return redirect()->route('portal.dashboard');
        }

        if ($request->boolean('ganti')) {
            $request->session()->forget(self::PENDING_KEY);
        }

        return Inertia::render('Public/Portal/Login', [
            'company' => CompanyBranding::data($settings),
            'pendingCode' => $request->session()->get(self::PENDING_KEY),
            'resendSeconds' => CustomerLoginService::RESEND_SECONDS,
        ]);
    }

    public function sendCode(Request $request, CustomerLoginService $login): RedirectResponse
    {
        $data = $request->validate(['customer_code' => ['required', 'string', 'max:40']]);
        $code = $login->normalize($data['customer_code']);

        $login->send($code);
        $request->session()->put(self::PENDING_KEY, $code);
        Toast::success('Jika kode pelanggan terdaftar, kode masuk sudah dikirim ke WhatsApp Anda.');

        return redirect()->route('portal.login');
    }

    public function verify(Request $request, CustomerLoginService $login): RedirectResponse
    {
        $data = $request->validate(['otp' => ['required', 'digits:6']]);
        $pending = $request->session()->get(self::PENDING_KEY);

        $customer = $pending ? $login->verify($pending, $data['otp']) : null;

        if ($customer === null) {
            throw ValidationException::withMessages(['otp' => 'Kode salah atau sudah kedaluwarsa. Minta kode baru bila perlu.']);
        }

        $request->session()->regenerate();
        $request->session()->forget(self::PENDING_KEY);
        $request->session()->put([
            EnsureCustomerPortalSession::SESSION_KEY => $customer->id,
            EnsureCustomerPortalSession::LOGGED_IN_AT => now()->timestamp,
        ]);

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([EnsureCustomerPortalSession::SESSION_KEY, EnsureCustomerPortalSession::LOGGED_IN_AT, self::PENDING_KEY]);
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
