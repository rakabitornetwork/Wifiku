<?php

namespace App\Services\Telegram;

use App\Jobs\Notification\SendTelegramMessageJob;
use App\Models\Customer;
use App\Models\Outage;
use App\Models\Router;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use Illuminate\Support\Str;
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

    /**
     * Isolir atau buka-isolir tidak berhasil setelah semua percobaan. Gagal membuka berarti pelanggan sudah bayar tetapi masih terputus.
     */
    public function isolationFailed(Customer $customer, bool $reopening, string $error): void
    {
        $customer->loadMissing('router:id,name');
        $router = $customer->router?->name ?? 'router';
        $headline = $reopening
            ? "🚨 Pelanggan sudah membayar tetapi GAGAL dibuka kembali: {$customer->name} ({$customer->customer_code})"
            : "🚫 Gagal mengisolir pelanggan: {$customer->name} ({$customer->customer_code})";

        $this->send('alert_isolation', "{$headline}
Router: {$router}
".Str::limit($error, 300).($reopening ? '
Buka manual di router atau coba lagi setelah router terjangkau.' : '
Isolir akan dicoba lagi pada penjadwalan berikutnya.'));
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
