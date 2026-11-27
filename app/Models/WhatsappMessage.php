<?php

namespace App\Models;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use Database\Factories\WhatsappMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'phone', 'type', 'body', 'status', 'attempts', 'error', 'is_sensitive', 'sent_at'])]
class WhatsappMessage extends Model
{
    /** @use HasFactory<WhatsappMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WhatsappMessageType::class,
            'status' => WhatsappMessageStatus::class,
            'attempts' => 'integer',
            'is_sensitive' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Pesan yang memuat kode atau password: setelah selesai diproses isinya diganti penanda agar tidak tersimpan terbaca.
     */
    public function redactIfSensitive(): void
    {
        if ($this->is_sensitive && $this->body !== TelegramMessage::REDACTED) {
            $this->update(['body' => TelegramMessage::REDACTED]);
        }
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
