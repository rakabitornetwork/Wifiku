<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Requests\AvatarRequest;
use App\Http\Requests\ProfilePhoneRequest;
use App\Models\User;
use App\Services\Auth\AvatarService;
use App\Services\Auth\PhoneVerificationService;
use App\Services\Whatsapp\AdminNotifier;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akun pengguna yang sedang login: saat ini hanya ganti password.
 */
class ProfileController extends Controller
{
    public function edit(Request $request, PhoneVerificationService $verification): Response
    {
        $user = $this->admin($request);
        $pending = $verification->pending($user);

        return Inertia::render('Profile/Edit', [
            'account' => [
                ...$user->only(['name', 'email', 'phone']),
                'avatar_url' => $user->avatarUrl(),
                'role' => $user->getRoleNames()->first(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            // Permintaan ganti nomor yang menunggu kode OTP; kodenya sendiri tidak pernah dikirim ke browser.
            'pendingPhone' => $pending === null ? null : [
                'phone' => $pending->phone,
                'expires_at' => $pending->expires_at->toIso8601String(),
                'resend_in' => $verification->secondsUntilResend($user),
                'attempts_left' => PhoneVerificationService::MAX_ATTEMPTS - $pending->attempts,
            ],
        ]);
    }

    public function updatePassword(Request $request, AdminNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], [
            'current_password.current_password' => 'Password saat ini salah.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ]);

        $user = $this->admin($request);
        $user->forceFill(['password' => $validated['password'], 'remember_token' => Str::random(60)])->save();

        // Sesi di perangkat lain berakhir; sesi ini tetap login dan ID-nya diganti.
        PasswordResetController::endSessions($user, $request->session()->getId());
        $request->session()->regenerate();

        activity('Pengguna')->performedOn($user)->causedBy($user)->log('Mengganti password sendiri');

        if (filled($user->phone)) {
            $notifier->passwordChanged($user, allSessionsEnded: false);
        }

        Toast::success('Password berhasil diganti. Sesi login di perangkat lain telah diakhiri.');

        return back();
    }

    /**
     * Langkah 1 ganti nomor WhatsApp. Nomor ini menerima tautan reset password, jadi perubahannya butuh
     * password saat ini, dan nomor baru harus dibuktikan milik pengguna lewat kode OTP yang dikirim ke nomor itu.
     * Menghapus nomor tidak butuh OTP (tidak ada nomor yang perlu dibuktikan).
     */
    public function updatePhone(ProfilePhoneRequest $request, PhoneVerificationService $verification, AdminNotifier $notifier): RedirectResponse
    {
        $user = $this->admin($request);
        $old = $user->phone;
        $new = $request->validated('phone');

        if ($old === $new) {
            $verification->cancel($user);
            Toast::success('Nomor WhatsApp tidak berubah.');

            return back();
        }

        if ($new === null) {
            $verification->cancel($user);
            $user->update(['phone' => null]);
            $notifier->phoneChanged($user, $old, null);
            Toast::success('Nomor WhatsApp dihapus.');

            return back();
        }

        $wait = $verification->secondsUntilResend($user);

        if ($wait > 0) {
            Toast::error("Kode baru saja dikirim. Tunggu {$wait} detik sebelum meminta lagi.");

            return back();
        }

        $verification->send($user, $new);

        Toast::success('Kode verifikasi dikirim ke WhatsApp nomor baru. Masukkan kodenya untuk menyimpan nomor.');

        return back();
    }

    /**
     * Langkah 2: nomor baru disimpan setelah kode OTP benar. Nomor lama dan baru diberi tahu.
     */
    public function verifyPhone(Request $request, PhoneVerificationService $verification, AdminNotifier $notifier): RedirectResponse
    {
        $user = $this->admin($request);
        $validated = $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'Kode terdiri dari 6 angka.']);

        $result = $verification->verify($user, $validated['code']);

        if ($result['status'] !== 'verified') {
            throw ValidationException::withMessages(['code' => match ($result['status']) {
                'invalid' => "Kode salah. Sisa percobaan: {$result['remaining']}.",
                'locked' => 'Percobaan habis. Minta kode baru.',
                default => 'Kode kedaluwarsa atau belum diminta. Minta kode baru.',
            }]);
        }

        $old = $user->phone;
        $user->update(['phone' => $result['phone']]);
        $notifier->phoneChanged($user, $old, $result['phone']);

        Toast::success('Nomor WhatsApp terverifikasi dan disimpan.');

        return back();
    }

    /**
     * Kirim ulang kode ke nomor yang sedang menunggu verifikasi (password sudah dibuktikan di langkah 1);
     * tidak bisa dipakai untuk mengirim ke nomor lain.
     */
    public function resendPhoneCode(Request $request, PhoneVerificationService $verification): RedirectResponse
    {
        $user = $this->admin($request);
        $pending = $verification->pending($user);

        if ($pending === null) {
            Toast::error('Tidak ada perubahan nomor yang menunggu verifikasi. Mulai lagi dari formulir nomor.');

            return back();
        }

        $wait = $verification->secondsUntilResend($user);

        if ($wait > 0) {
            Toast::error("Kode baru saja dikirim. Tunggu {$wait} detik sebelum meminta lagi.");

            return back();
        }

        $verification->send($user, $pending->phone);

        Toast::success('Kode verifikasi dikirim ulang.');

        return back();
    }

    public function cancelPhone(Request $request, PhoneVerificationService $verification): RedirectResponse
    {
        $verification->cancel($this->admin($request));

        Toast::success('Perubahan nomor dibatalkan.');

        return back();
    }

    public function updateAvatar(AvatarRequest $request, AvatarService $avatars): RedirectResponse
    {
        $user = $this->admin($request);

        $avatars->replace($user, $request->file('avatar'));

        activity('Pengguna')->performedOn($user)->causedBy($user)->log('Mengubah foto profil');

        Toast::success('Foto profil diperbarui.');

        return back();
    }

    public function removeAvatar(Request $request, AvatarService $avatars): RedirectResponse
    {
        $user = $this->admin($request);

        if (filled($user->avatar_path)) {
            $avatars->remove($user);

            activity('Pengguna')->performedOn($user)->causedBy($user)->log('Menghapus foto profil');
        }

        Toast::success('Foto profil dihapus.');

        return back();
    }

    /**
     * Pengguna panel admin yang sedang login; sesi guard lain (mis. member VPN) ditolak.
     */
    private function admin(Request $request): User
    {
        return $request->user('web') ?? abort(403);
    }
}
