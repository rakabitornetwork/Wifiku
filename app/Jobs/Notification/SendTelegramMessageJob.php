<?php

namespace App\Jobs\Notification;

use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim satu notifikasi admin ke Telegram lewat antrean agar proses yang memicunya (cek router, buat gangguan) tidak menunggu.
 */
class SendTelegramMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public string $text) {}

    public function handle(TelegramClient $telegram): void
    {
        // Pengaturan bisa dimatikan setelah pesan diantrekan.
        if (! $telegram->isEnabled()) {
            return;
        }

        $telegram->sendMessage($this->text);
    }

    public function failed(?\Throwable $exception): void
    {
        if ($exception instanceof TelegramDeliveryException) {
            Log::warning('Notifikasi Telegram gagal terkirim.', ['error' => $exception->getMessage()]);
        }
    }
}
