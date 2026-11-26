<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCustomerPortalSession;
use App\Models\Customer;
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
 * Login portal pelanggan PPPoE: nomor WhatsApp terdaftar lalu kode OTP yang dikirim ke nomor itu.
 * Bila satu nomor dipakai beberapa sambungan, pelanggan memilih sambungan setelah OTP benar.
 * Terpisah dari login admin dan member VPN.
 */
class CustomerPortalAuthController extends Controller
{
    private const PENDING_KEY = 'portal.pending_phone';

    public function showLogin(Request $request, SettingService $settings): Response|RedirectResponse
    {
        if ($request->session()->has(EnsureCustomerPortalSession::SESSION_KEY)) {
            return redirect()->route('portal.dashboard');
        }

        if ($request->boolean('ganti')) {
            $request->session()->forget(self::PENDING_KEY);
        }

        $pending = $request->session()->get(self::PENDING_KEY);

        return Inertia::render('Public/Portal/Login', [
            'company' => CompanyBranding::data($settings),
            'pendingPhone' => $pending ? CustomerLoginService::mask($pending) : null,
            'resendSeconds' => CustomerLoginService::RESEND_SECONDS,
        ]);
    }

    public function sendCode(Request $request, CustomerLoginService $login): RedirectResponse
    {
        $data = $request->validate(['whatsapp' => ['required', 'string', 'max:30']]);
        $phone = CustomerLoginService::normalizePhone($data['whatsapp']);

        if ($phone === null) {
            throw ValidationException::withMessages(['whatsapp' => 'Nomor WhatsApp tidak valid. Contoh: 08123456789.']);
        }

        $login->send($phone);
        $request->session()->put(self::PENDING_KEY, $phone);
        Toast::success('Jika nomor ini terdaftar, kode masuk sudah dikirim ke WhatsApp Anda.');

        return redirect()->route('portal.login');
    }

    public function resend(Request $request, CustomerLoginService $login): RedirectResponse
    {
        if ($phone = $request->session()->get(self::PENDING_KEY)) {
            $login->send($phone);
            Toast::success('Jika nomor ini terdaftar, kode masuk sudah dikirim ulang.');
        }

        return redirect()->route('portal.login');
    }

    public function verify(Request $request, CustomerLoginService $login): RedirectResponse
    {
        $data = $request->validate(['otp' => ['required', 'digits:6']]);
        $phone = $request->session()->get(self::PENDING_KEY);

        if (! $phone || ! $login->verify($phone, $data['otp'])) {
            throw ValidationException::withMessages(['otp' => 'Kode salah atau sudah kedaluwarsa. Minta kode baru bila perlu.']);
        }

        $customers = $login->customersFor($phone);

        $request->session()->regenerate();
        $request->session()->forget(self::PENDING_KEY);
        $request->session()->put([
            EnsureCustomerPortalSession::PHONE_KEY => $phone,
            EnsureCustomerPortalSession::LOGGED_IN_AT => now()->timestamp,
        ]);

        if ($customers->count() === 1) {
            $request->session()->put(EnsureCustomerPortalSession::SESSION_KEY, $customers->first()->id);

            return redirect()->route('portal.dashboard');
        }

        return redirect()->route('portal.choose');
    }

    /**
     * Daftar sambungan yang memakai nomor yang sudah diverifikasi; tidak butuh sambungan terpilih.
     */
    public function choose(Request $request, CustomerLoginService $login, SettingService $settings): Response|RedirectResponse
    {
        $phone = $this->verifiedPhone($request);

        if ($phone === null) {
            return redirect()->route('portal.login');
        }

        return Inertia::render('Public/Portal/Choose', [
            'company' => CompanyBranding::data($settings),
            'accounts' => $login->customersFor($phone)->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'package' => $customer->package?->name,
                'address' => collect([$customer->address, $customer->village])->filter()->implode(', ') ?: null,
                'status' => $customer->status->value,
            ])->all(),
        ]);
    }

    public function select(Request $request, CustomerLoginService $login): RedirectResponse
    {
        $phone = $this->verifiedPhone($request);
        $data = $request->validate(['customer_id' => ['required', 'integer']]);

        if ($phone === null) {
            return redirect()->route('portal.login');
        }

        abort_unless($login->customersFor($phone)->contains('id', $data['customer_id']), 404);

        $request->session()->put(EnsureCustomerPortalSession::SESSION_KEY, (int) $data['customer_id']);

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([EnsureCustomerPortalSession::SESSION_KEY, EnsureCustomerPortalSession::PHONE_KEY, EnsureCustomerPortalSession::LOGGED_IN_AT, self::PENDING_KEY]);
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    /**
     * Nomor hasil login OTP yang masih berlaku (sesi belum melewati batas waktu mutlak), atau null.
     */
    private function verifiedPhone(Request $request): ?string
    {
        $session = $request->session();
        $fresh = now()->timestamp - (int) $session->get(EnsureCustomerPortalSession::LOGGED_IN_AT) < EnsureCustomerPortalSession::MAX_HOURS * 3600;

        return $fresh ? $session->get(EnsureCustomerPortalSession::PHONE_KEY) : null;
    }
}
