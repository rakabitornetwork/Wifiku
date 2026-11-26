<?php

namespace App\Services\Telegram;

use App\Jobs\Notification\SendTelegramMessageJob;
use App\Models\Outage;
use App\Models\Router;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use Throwable;

/**
 * Notifikasi peringatan untuk admin lewat Telegram. Setiap jenis punya saklar sendiri di Pengaturan dan hanya
 * diantrekan bila Telegram aktif; kegagalan di sini tidak boleh mengganggu proses yang memicunya.
 */
class TelegramNotifier
{
    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly SettingService $settings,
    ) {}

    public function routerOffline(Router $router): void
    {
        $this->send('alert_router', "🔴 Router offline: {$router->name} ({$router->host})".($router->last_error ? "\n{$router->last_error}" : ''));
    }

    public function routerRecovered(Router $router, ?int $downMinutes): void
    {
        $this->send('alert_router', "🟢 Router pulih: {$router->name} ({$router->host})".($downMinutes !== null ? "\nSempat putus ±{$downMinutes} menit." : ''));
    }

    public function outageOpened(Outage $outage): void
    {
        $outage->loadMissing('odp:id,code,name');
        $odp = $outage->odp ? "{$outage->odp->code} · {$outage->odp->name}" : 'ODP';

        $this->send('alert_outage', "⚠️ Gangguan baru: {$outage->title}\nLokasi: {$odp}");
    }

    public function whatsappFailed(WhatsappMessage $message): void
    {
        $this->send('alert_whatsapp', "📵 Pesan WhatsApp gagal terkirim ke {$message->phone} (jenis: {$message->type->value}).".($message->error ? "\n{$message->error}" : ''));
    }

    private function send(string $switch, string $text): void
    {
        try {
            if (! $this->telegram->isEnabled() || $this->settings->get('telegram', $switch) === '0') {
                return;
            }

            SendTelegramMessageJob::dispatch($text);
        } catch (Throwable) {
            // Notifikasi adalah tambahan; proses utama tetap berjalan.
        }
    }
}
