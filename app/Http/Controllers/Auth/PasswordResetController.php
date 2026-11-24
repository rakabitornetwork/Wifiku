<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Whatsapp\AdminNotifier;
use App\Support\Toast;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Lupa/reset password pengguna panel admin. Tautan dikirim ke WhatsApp pengguna (kolom telepon);
 * pengguna tanpa nomor memakai email bila email sudah dikonfigurasi.
 */
class PasswordResetController extends Controller
{
    public function showForgot(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Jawaban selalu sama, apa pun hasilnya, agar tidak bisa dipakai menebak email yang terdaftar.
     */
    public function sendLink(Request $request, AdminNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        try {
            Password::sendResetLink(
                ['email' => strtolower(trim($validated['email'])), 'is_active' => true],
                fn (User $user, string $token) => self::deliver($user, $token, $notifier),
            );
        } catch (Throwable $exception) {
            // Gagal kirim (mis. SMTP mati) tidak boleh membocorkan apakah email terdaftar.
            Log::warning('Gagal mengirim tautan reset password admin.', ['error' => $exception->getMessage()]);
        }

        Toast::success('Jika email terdaftar, tautan reset password telah dikirim. Cek WhatsApp atau email Anda.');

        return redirect()->route('login');
    }

    /**
     * Kirim tautan lewat WhatsApp bila pengguna punya nomor, selain itu lewat email.
     */
    public static function deliver(User $user, string $token, AdminNotifier $notifier): void
    {
        if (filled($user->phone)) {
            $notifier->passwordReset($user, route('password.reset', ['token' => $token, 'email' => $user->email]));

            return;
        }

        $user->sendPasswordResetNotification($token);
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request, AdminNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            ['email' => strtolower(trim($validated['email'])), 'password' => $validated['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $validated['token']],
            function (User $user, string $password) use ($notifier): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                self::endSessions($user);
                event(new PasswordReset($user));

                activity('Pengguna')->performedOn($user)->causedBy($user)->log('Password direset lewat tautan reset');

                if (filled($user->phone)) {
                    $notifier->passwordChanged($user);
                }
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan tidak valid atau sudah kedaluwarsa. Minta tautan baru.']);
        }

        Toast::success('Password berhasil diubah. Silakan masuk dengan password baru.');

        return redirect()->route('login');
    }

    /**
     * Akhiri sesi login pengguna (kecuali $exceptSessionId) agar sesi lama yang mungkin sudah diambil orang lain tidak berlaku lagi.
     */
    public static function endSessions(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                ->delete();
        }
    }
}
