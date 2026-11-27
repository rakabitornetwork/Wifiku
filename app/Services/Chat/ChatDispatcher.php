<?php

namespace App\Services\Chat;

use App\Enums\WhatsappMessageType;
use App\Models\ChatSession;
use App\Models\TelegramMessage;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use App\Services\Whatsapp\WhatsappNotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim balasan bot ke kanal asalnya. WhatsApp lewat outbox (dibatasi rate limiter); Telegram langsung ke Bot API.
 * Kedua kanal tercatat di riwayat pesan; balasan yang memuat password disamarkan.
 */
class ChatDispatcher
{
    public function __construct(
        private readonly WhatsappNotificationService $whatsapp,
        private readonly TelegramClient $telegram,
    ) {}

    /**
     * @param  list<ChatReply>  $replies
     */
    public function send(ChatMessage $message, array $replies): void
    {
        foreach ($replies as $reply) {
            if ($message->channel === ChatMessage::CHANNEL_WHATSAPP) {
                $this->whatsapp->queue($message->senderId, WhatsappMessageType::Chat, $reply->text, sensitive: $reply->sensitive);

                continue;
            }

            $this->sendTelegram($message, $reply);
        }
    }

    private function sendTelegram(ChatMessage $message, ChatReply $reply): void
    {
        $log = TelegramMessage::create([
            'customer_id' => ChatSession::query()->where('channel', ChatMessage::CHANNEL_TELEGRAM)->where('sender_id', $message->senderId)->value('customer_id'),
            'chat_id' => $message->senderId,
            'type' => WhatsappMessageType::Chat->value,
            'body' => $reply->sensitive ? TelegramMessage::REDACTED : $reply->text,
            'status' => 'queued',
            'is_sensitive' => $reply->sensitive,
        ]);

        try {
            $log->increment('attempts');
            $this->telegram->sendTo($message->senderId, $reply->text, $this->markup($reply));
            $log->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);

            if ($reply->deleteIncoming && $message->messageId !== null) {
                $this->telegram->deleteMessage($message->senderId, $message->messageId);
            }
        } catch (TelegramDeliveryException $exception) {
            // Penghapusan pesan yang gagal tidak boleh membatalkan balasan; kegagalan kirim dicatat tanpa token.
            $log->update(['status' => $log->status === 'sent' ? 'sent' : 'failed', 'error' => $log->status === 'sent' ? null : mb_substr($exception->getMessage(), 0, 500)]);
            Log::warning('Balasan bot Telegram gagal.', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function markup(ChatReply $reply): ?array
    {
        if ($reply->requestContact) {
            return ['keyboard' => [[['text' => '📱 Bagikan nomor saya', 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true];
        }

        if ($reply->buttons !== []) {
            return ['inline_keyboard' => array_map(fn (array $row): array => array_map(
                fn (array $button): array => isset($button['url']) ? ['text' => $button['label'], 'url' => $button['url']] : ['text' => $button['label'], 'callback_data' => $button['data'] ?? ''],
                $row,
            ), $reply->buttons)];
        }

        return $reply->removeKeyboard ? ['remove_keyboard' => true] : null;
    }
}
