<?php

namespace App\Http\Controllers\Vpn;

use App\Http\Controllers\Controller;
use App\Models\VpnMember;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\VpnNotifier;
use App\Support\Toast;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lupa/reset password pelanggan VPN. Tautan dikirim lewat WhatsApp ke nomor yang didaftarkan.
 */
class VpnMemberPasswordController extends Controller
{
    public function showForgot(SettingService $settings): Response
    {
        return Inertia::render('Public/Vpn/ForgotPassword', ['company' => $settings->get('company', 'name', config('app.name'))]);
    }

    /**
     * Jawaban selalu sama, apa pun hasilnya, agar tidak bisa dipakai menebak email yang terdaftar.
     */
    public function sendLink(Request $request, VpnNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        Password::broker('members')->sendResetLink(
            ['email' => strtolower(trim($validated['email'])), 'is_active' => true],
            function (VpnMember $member, string $token) use ($notifier): void {
                $notifier->passwordReset($member, route('vpn-member.password.reset', ['token' => $token, 'email' => $member->email]));
            },
        );

        Toast::success('Jika email terdaftar, tautan reset password dikirim ke WhatsApp Anda. Cek pesan masuk dalam beberapa menit.');

        return redirect()->route('portal.login', ['layanan' => 'vpn']);
    }

    public function showReset(Request $request, string $token, SettingService $settings): Response
    {
        return Inertia::render('Public/Vpn/ResetPassword', [
            'company' => $settings->get('company', 'name', config('app.name')),
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function reset(Request $request, VpnNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::broker('members')->reset(
            ['email' => strtolower(trim($validated['email'])), 'password' => $validated['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $validated['token']],
            function (VpnMember $member, string $password) use ($notifier): void {
                $member->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                event(new PasswordReset($member));
                $notifier->passwordChanged($member);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan tidak valid atau sudah kedaluwarsa. Minta tautan baru.']);
        }

        Toast::success('Password berhasil diubah. Silakan masuk dengan password baru.');

        return redirect()->route('portal.login', ['layanan' => 'vpn']);
    }
}
