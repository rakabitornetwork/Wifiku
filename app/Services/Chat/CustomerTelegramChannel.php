<?php

namespace App\Services\Chat;

use App\Enums\WhatsappMessageType;
use App\Jobs\Notification\SendTelegramCustomerMessageJob;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Models\TelegramMessage;
use App\Services\Portal\CustomerLoginService;
use App\Services\Settings\SettingService;
use App\Services\Telegram\TelegramClient;

/**
 * Pesan otomatis ke pelanggan lewat Telegram. Hanya pelanggan yang sudah menautkan Telegram (membagikan nomornya ke bot)
 * yang bisa dijangkau; tautan dicari dari nomor WhatsApp terdaftar, jadi nomor yang berubah otomatis memutus tautan lama.
 */
class CustomerTelegramChannel
{
    public const OFF = 'off';

    public const BOTH = 'both';

    public const PREFER = 'prefer';

    public function __construct(
        private readonly SettingService $settings,
        private readonly TelegramClient $telegram,
    ) {}

    /**
     * Mode pengiriman efektif; "off" bila bot pelanggan Telegram belum aktif atau token belum diisi.
     */
    public function mode(): string
    {
        if ($this->settings->get('telegram', 'bot_enabled') !== '1' || ! $this->telegram->hasToken()) {
            return self::OFF;
        }

        return in_array($mode = (string) $this->settings->get('telegram', 'customer_delivery'), [self::OFF, self::PREFER], true) ? $mode : self::BOTH;
    }

    public function prefersTelegram(): bool
    {
        return $this->mode() === self::PREFER;
    }

    /**
     * Chat ID Telegram yang tertaut ke nomor pelanggan ini.
     *
     * @return list<string>
     */
    public function chatIdsFor(Customer $customer): array
    {
        $phone = CustomerLoginService::normalizePhone($customer->whatsapp);

        if ($phone === null) {
            return [];
        }

        return ChatSession::query()->where('channel', ChatMessage::CHANNEL_TELEGRAM)->where('phone', $phone)->pluck('sender_id')->map(fn (mixed $id): string => (string) $id)->all();
    }

    /**
     * Antrekan $text ke semua chat Telegram tertaut milik pelanggan.
     *
     * @return bool true bila ada chat Telegram yang dituju.
     */
    public function send(Customer $customer, string $text, ?WhatsappMessageType $type = null): bool
    {
        if ($this->mode() === self::OFF) {
            return false;
        }

        $chats = $this->chatIdsFor($customer);

        foreach ($chats as $chatId) {
            $log = TelegramMessage::create(['customer_id' => $customer->id, 'chat_id' => $chatId, 'type' => $type?->value, 'body' => $text, 'status' => 'queued']);

            SendTelegramCustomerMessageJob::dispatch($log->id);
        }

        return $chats !== [];
    }
}
