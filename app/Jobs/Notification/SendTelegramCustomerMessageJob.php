<?php

namespace App\Jobs\Notification;

use App\Models\ChatSession;
use App\Services\Chat\ChatMessage;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Mengirim satu pesan otomatis ke chat Telegram pelanggan. Bila pelanggan memblokir bot atau chat-nya hilang, tautannya
 * dicabut agar tidak dicoba lagi; galat sementara dicoba ulang.
 */
class SendTelegramCustomerMessageJob implements ShouldQueue
{
    use Queueable;

    private const GONE = ['blocked by the user', 'chat not found', 'user is deactivated', 'bot was kicked'];

    public int $tries = 3;

    public int $timeout = 20;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public string $chatId, public string $text) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('telegram')];
    }

    public function handle(TelegramClient $telegram): void
    {
        try {
            $telegram->sendTo($this->chatId, $this->text);
        } catch (TelegramDeliveryException $exception) {
            $message = strtolower($exception->getMessage());

            foreach (self::GONE as $needle) {
                if (str_contains($message, $needle)) {
                    ChatSession::query()->where('channel', ChatMessage::CHANNEL_TELEGRAM)->where('sender_id', $this->chatId)->update(['phone' => null, 'customer_id' => null, 'state' => ChatSession::IDLE, 'payload' => null]);

                    return;
                }
            }

            throw $exception;
        }
    }
}
