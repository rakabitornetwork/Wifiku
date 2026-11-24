<?php

namespace App\Services\Auth;

use App\Models\AdminPhoneVerification;
use App\Models\User;
use App\Services\Whatsapp\AdminNotifier;
use Illuminate\Support\Facades\Hash;

/**
 * Verifikasi nomor WhatsApp baru pengguna admin dengan kode OTP 6 digit yang dikirim ke nomor itu.
 * Nomor baru baru dipakai setelah kodenya dimasukkan dengan benar.
 */
class PhoneVerificationService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public function __construct(private readonly AdminNotifier $notifier) {}

    public function pending(User $user): ?AdminPhoneVerification
    {
        $pending = AdminPhoneVerification::query()->where('user_id', $user->id)->first();

        if ($pending !== null && $pending->expires_at->isPast()) {
            $pending->delete();

            return null;
        }

        return $pending;
    }

    /**
     * Detik yang masih harus ditunggu sebelum kode baru boleh dikirim (0 bila sudah boleh).
     * Berlaku per pengguna, bukan per nomor, agar form ini tidak bisa dipakai membanjiri banyak nomor.
     */
    public function secondsUntilResend(User $user): int
    {
        $pending = $this->pending($user);

        return $pending === null ? 0 : max(0, self::RESEND_SECONDS - (int) $pending->sent_at->diffInSeconds(now(), true));
    }

    /**
     * Buat kode baru untuk $phone dan kirim ke nomor itu; permintaan sebelumnya otomatis diganti.
     */
    public function send(User $user, string $phone): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        AdminPhoneVerification::query()->updateOrCreate(['user_id' => $user->id], [
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => now(),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->notifier->phoneVerificationCode($user, $phone, $code, self::TTL_MINUTES);
    }

    /**
     * Periksa kode. Mengembalikan nomor yang terverifikasi pada keberhasilan; selain itu salah satu status:
     * `none` (tidak ada permintaan/kedaluwarsa), `locked` (percobaan habis), atau `invalid` (kode salah).
     *
     * @return array{status: 'verified', phone: string}|array{status: 'none'|'locked'}|array{status: 'invalid', remaining: int}
     */
    public function verify(User $user, string $code): array
    {
        $pending = $this->pending($user);

        if ($pending === null) {
            return ['status' => 'none'];
        }

        if (! Hash::check($code, $pending->code_hash)) {
            $pending->increment('attempts');

            if ($pending->attempts >= self::MAX_ATTEMPTS) {
                $pending->delete();

                return ['status' => 'locked'];
            }

            return ['status' => 'invalid', 'remaining' => self::MAX_ATTEMPTS - $pending->attempts];
        }

        $phone = $pending->phone;
        $pending->delete();

        return ['status' => 'verified', 'phone' => $phone];
    }

    public function cancel(User $user): void
    {
        AdminPhoneVerification::query()->where('user_id', $user->id)->delete();
    }
}
