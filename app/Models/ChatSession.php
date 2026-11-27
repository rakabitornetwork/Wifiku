<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keadaan percakapan bot pelanggan untuk satu pengirim di satu kanal ('whatsapp' atau 'telegram').
 * Untuk WhatsApp sender_id adalah nomor (62xxx); untuk Telegram adalah chat ID, dan phone baru terisi setelah nomor diverifikasi.
 */
#[Fillable(['channel', 'sender_id', 'phone', 'customer_id', 'state', 'payload', 'expires_at', 'last_message_at'])]
class ChatSession extends Model
{
    public const IDLE = 'idle';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Berisi password WiFi yang menunggu konfirmasi, jadi selalu terenkripsi.
            'payload' => 'encrypted:array',
            'expires_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Kembali ke menu: langkah dan data sementara dihapus, nomor dan sambungan terpilih dipertahankan.
     */
    public function resetFlow(): void
    {
        $this->state = self::IDLE;
        $this->payload = null;
        $this->expires_at = null;
    }
}
