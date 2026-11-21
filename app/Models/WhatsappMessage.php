<?php

namespace App\Models;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use Database\Factories\WhatsappMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'phone', 'type', 'body', 'status', 'attempts', 'error', 'sent_at'])]
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
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
