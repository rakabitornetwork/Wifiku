<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\User;
use App\Services\Whatsapp\AdminNotifier;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akun pengguna yang sedang login: saat ini hanya ganti password.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $this->admin($request);

        return Inertia::render('Profile/Edit', [
            'account' => [
                ...$user->only(['name', 'email', 'phone']),
                'role' => $user->getRoleNames()->first(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
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
     * Pengguna panel admin yang sedang login; sesi guard lain (mis. member VPN) ditolak.
     */
    private function admin(Request $request): User
    {
        return $request->user('web') ?? abort(403);
    }
}
