<?php

namespace App\Services\Whatsapp;

use App\Enums\WhatsappMessageType;
use App\Models\User;
use App\Services\Settings\SettingService;

/**
 * Pesan WhatsApp untuk pengguna panel admin (saat ini hanya reset password).
 */
class AdminNotifier
{
    public function __construct(
        private readonly WhatsappNotificationService $whatsapp,
        private readonly SettingService $settings,
    ) {}

    public function passwordReset(User $user, string $url): void
    {
        $this->whatsapp->queue((string) $user->phone, WhatsappMessageType::PasswordReset, implode("\n", [
            "Halo {$user->name},",
            '',
            "Kami menerima permintaan reset password akun panel {$this->company()} Anda.",
            'Buka tautan ini untuk membuat password baru (berlaku 60 menit):',
            $url,
            '',
            'Abaikan pesan ini bila Anda tidak memintanya; password Anda tidak berubah.',
        ]));
    }

    public function passwordChanged(User $user, bool $allSessionsEnded = true): void
    {
        $this->whatsapp->queue((string) $user->phone, WhatsappMessageType::PasswordReset, implode("\n", [
            "Halo {$user->name},",
            '',
            "Password akun panel {$this->company()} Anda baru saja diubah dan ".($allSessionsEnded ? 'semua sesi login Anda diakhiri.' : 'sesi login di perangkat lain diakhiri.'),
            'Bila bukan Anda yang melakukannya, segera hubungi Owner.',
        ]));
    }

    /**
     * Beri tahu nomor lama dan nomor baru bahwa nomor WhatsApp akun diganti (nomor ini menerima tautan reset password).
     */
    public function phoneChanged(User $user, ?string $oldPhone, ?string $newPhone): void
    {
        $company = $this->company();

        if (filled($oldPhone)) {
            $this->whatsapp->queue($oldPhone, WhatsappMessageType::AccountSecurity, implode("\n", [
                "Halo {$user->name},",
                '',
                $newPhone === null
                    ? "Nomor WhatsApp akun panel {$company} Anda dihapus."
                    : "Nomor WhatsApp akun panel {$company} Anda diganti ke {$this->mask($newPhone)}.",
                'Bila bukan Anda yang melakukannya, segera hubungi Owner.',
            ]));
        }

        if (filled($newPhone)) {
            $this->whatsapp->queue($newPhone, WhatsappMessageType::AccountSecurity, implode("\n", [
                "Halo {$user->name},",
                '',
                "Nomor ini sekarang terdaftar sebagai WhatsApp akun panel {$company} ({$user->email}). Tautan reset password akan dikirim ke sini.",
            ]));
        }
    }

    /**
     * 6281234567890 → 62••••••7890 agar nomor lengkap tidak terbuka di pesan.
     */
    private function mask(string $phone): string
    {
        return substr($phone, 0, 2).str_repeat('•', max(0, strlen($phone) - 6)).substr($phone, -4);
    }

    private function company(): string
    {
        return (string) $this->settings->get('company', 'name');
    }
}
