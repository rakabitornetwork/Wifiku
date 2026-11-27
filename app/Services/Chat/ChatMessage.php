<?php

namespace App\Services\Chat;

/**
 * Pesan masuk dari pelanggan yang sudah dinormalkan, sama untuk WhatsApp dan Telegram.
 */
final readonly class ChatMessage
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_TELEGRAM = 'telegram';

    /** Teks khusus: pengguna Telegram membagikan kontak milik orang lain, bukan kontaknya sendiri. */
    public const FOREIGN_CONTACT = '__kontak_orang_lain__';

    /**
     * @param  string  $senderId  WhatsApp: nomor 62xxx. Telegram: chat ID.
     * @param  string|null  $contactPhone  Hanya Telegram: nomor dari tombol Bagikan kontak yang terbukti milik pengirim sendiri.
     * @param  string|null  $callbackId  Hanya Telegram: id tekan tombol, untuk menghentikan indikator memuat.
     */
    public function __construct(
        public string $channel,
        public string $senderId,
        public ?string $text = null,
        public ?string $messageId = null,
        public ?string $contactPhone = null,
        public ?string $callbackId = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['channel'],
            (string) $data['senderId'],
            isset($data['text']) ? (string) $data['text'] : null,
            isset($data['messageId']) ? (string) $data['messageId'] : null,
            isset($data['contactPhone']) ? (string) $data['contactPhone'] : null,
            isset($data['callbackId']) ? (string) $data['callbackId'] : null,
        );
    }
}
