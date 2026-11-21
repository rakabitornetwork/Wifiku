<?php

namespace App\Jobs\Notification;

use App\Models\WhatsappMessage;
use App\Services\Whatsapp\WhatsappNotificationService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Mengirim satu pesan WhatsApp melalui WhatsappNotificationService.
 * Dibatasi rate limiter "whatsapp" agar pengiriman massal tidak memicu blokir nomor.
 */
class SendWhatsappMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Jumlah galat gateway yang diizinkan; penundaan rate limit tidak dihitung.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 20;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     */
    public function __construct(public WhatsappMessage $whatsappMessage) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('whatsapp')];
    }

    /**
     * Pesan massal tetap dikirim walau antre lama karena rate limit.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHours(6);
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsappNotificationService $whatsapp): void
    {
        $whatsapp->send($this->whatsappMessage->fresh() ?? $this->whatsappMessage);
    }
}
