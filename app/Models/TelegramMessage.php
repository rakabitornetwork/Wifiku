<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pesan Telegram ke pelanggan (otomatis atau balasan bot) beserta status pengirimannya.
 */
#[Fillable(['customer_id', 'chat_id', 'type', 'body', 'status', 'attempts', 'error', 'is_sensitive', 'sent_at'])]
class TelegramMessage extends Model
{
    public const REDACTED = '[Isi disembunyikan: memuat kode, tautan, atau password rahasia]';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_sensitive' => 'boolean', 'attempts' => 'integer', 'sent_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
