<?php

namespace App\Services\Whatsapp;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Jobs\Notification\SendWhatsappMessageJob;
use App\Models\Customer;
use App\Models\WhatsappMessage;
use Illuminate\Support\Str;

/**
 * Pengiriman WhatsApp lewat Evolution API (Baileys).
 * Pesan selalu dicatat di outbox lalu dikirim lewat Queue agar UI tidak menunggu gateway.
 */
class WhatsappNotificationService
{
    public function __construct(private readonly EvolutionApiClient $client) {}

    /**
     * Catat pesan di outbox dan antrekan pengirimannya.
     */
    public function queue(string $phone, WhatsappMessageType $type, string $body, ?Customer $customer = null): WhatsappMessage
    {
        $message = WhatsappMessage::create([
            'customer_id' => $customer?->id,
            'phone' => $phone,
            'type' => $type,
            'body' => $body,
            'status' => WhatsappMessageStatus::Queued,
        ]);

        SendWhatsappMessageJob::dispatch($message);

        return $message;
    }

    /**
     * Kirim satu pesan yang sudah tercatat di outbox.
     *
     * @throws WhatsappDeliveryException Gateway tidak terjangkau atau menolak; job akan mencoba ulang.
     */
    public function send(WhatsappMessage $message): void
    {
        if ($message->status === WhatsappMessageStatus::Sent) {
            return;
        }

        if (! $this->client->isConfigured()) {
            // Bukan galat sementara: tidak perlu dicoba ulang sampai gateway dikonfigurasi.
            $message->update(['status' => WhatsappMessageStatus::Failed, 'error' => 'WA Gateway belum dikonfigurasi.']);

            return;
        }

        $message->increment('attempts');

        try {
            $this->client->sendText($message->phone, $message->body);
        } catch (WhatsappDeliveryException $exception) {
            $message->update(['status' => WhatsappMessageStatus::Failed, 'error' => Str::limit($exception->getMessage(), 500)]);

            throw new WhatsappDeliveryException("Gagal mengirim WhatsApp #{$message->id}: {$exception->getMessage()}", previous: $exception);
        }

        $message->update(['status' => WhatsappMessageStatus::Sent, 'sent_at' => now(), 'error' => null]);
    }
}
