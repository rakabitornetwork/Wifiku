<?php

namespace App\Jobs\Notification;

use App\Services\Chat\ChatBot;
use App\Services\Chat\ChatDispatcher;
use App\Services\Chat\ChatMessage;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Memproses satu pesan masuk bot pelanggan di antrean, sehingga webhook bisa langsung dijawab 200
 * walau membaca status ONU dari GenieACS bisa memakan beberapa detik.
 */
class HandleChatMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * @param  array<string, string|null>  $message
     */
    public function __construct(public array $message) {}

    public function handle(ChatBot $bot, ChatDispatcher $dispatcher, TelegramClient $telegram): void
    {
        $message = ChatMessage::fromArray($this->message);

        if ($message->callbackId !== null) {
            try {
                $telegram->answerCallbackQuery($message->callbackId);
            } catch (TelegramDeliveryException) {
                // Indikator memuat di tombol hanya kosmetik.
            }
        }

        $dispatcher->send($message, $bot->handle($message));
    }
}
