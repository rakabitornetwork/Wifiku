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

    private function company(): string
    {
        return (string) $this->settings->get('company', 'name');
    }
}
