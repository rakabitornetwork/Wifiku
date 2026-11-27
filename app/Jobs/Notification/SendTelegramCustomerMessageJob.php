<?php

namespace App\Jobs\Notification;

use App\Models\ChatSession;
use App\Models\TelegramMessage;
use App\Services\Chat\ChatMessage;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Mengirim satu pesan otomatis ke chat Telegram pelanggan dan memperbarui statusnya di riwayat pesan. Bila pelanggan
 * memblokir bot atau chat-nya hilang, tautannya dicabut agar tidak dicoba lagi; galat sementara dicoba ulang.
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

    public function __construct(public int $messageId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('telegram')];
    }

    public function handle(TelegramClient $telegram): void
    {
        $message = TelegramMessage::find($this->messageId);

        if ($message === null || $message->status === 'sent') {
            return;
        }

        $message->increment('attempts');

        try {
            $telegram->sendTo($message->chat_id, $message->body);
        } catch (TelegramDeliveryException $exception) {
            $error = mb_substr($exception->getMessage(), 0, 500);

            foreach (self::GONE as $needle) {
                if (str_contains(strtolower($error), $needle)) {
                    ChatSession::query()->where('channel', ChatMessage::CHANNEL_TELEGRAM)->where('sender_id', $message->chat_id)->update(['phone' => null, 'customer_id' => null, 'state' => ChatSession::IDLE, 'payload' => null]);
                    $message->update(['status' => 'failed', 'error' => "{$error} (tautan Telegram pelanggan dicabut)"]);

                    return;
                }
            }

            $message->update(['status' => 'failed', 'error' => $error]);

            throw $exception;
        }

        $message->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
    }
}
