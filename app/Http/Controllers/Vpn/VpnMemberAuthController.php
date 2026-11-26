<?php

namespace App\Http\Controllers\Vpn;

use App\Http\Controllers\Controller;
use App\Http\Requests\VpnMemberRegisterRequest;
use App\Models\VpnMember;
use App\Services\Settings\SettingService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VpnMemberAuthController extends Controller
{
    public function showRegister(SettingService $settings): Response|RedirectResponse
    {
        if (! $this->registrationOpen($settings)) {
            Toast::error('Pendaftaran VPN Tunnel sedang ditutup.');

            return redirect()->route('vpn-member.landing');
        }

        return Inertia::render('Public/Vpn/Register', ['company' => $settings->get('company', 'name', config('app.name'))]);
    }

    public function register(VpnMemberRegisterRequest $request, SettingService $settings): RedirectResponse
    {
        abort_unless($this->registrationOpen($settings), 403, 'Pendaftaran ditutup.');

        $member = VpnMember::create($request->safe()->only(['name', 'email', 'whatsapp', 'password']));

        Auth::guard('member')->login($member);
        $request->session()->regenerate();

        Toast::success('Pendaftaran berhasil. Buat akun VPN pertama Anda.');

        return redirect()->route('vpn-member.dashboard');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::guard('member')->attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Email atau password salah, atau akun dinonaktifkan.']);
        }

        $request->session()->regenerate();
        $request->user('member')->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('vpn-member.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('member')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vpn-member.landing');
    }

    private function registrationOpen(SettingService $settings): bool
    {
        return $settings->get('vpn', 'member_registration_open') === '1';
    }
}
