<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Kode OTP login portal pelanggan untuk satu nomor WhatsApp (format 62xxx); disimpan sebagai hash.
 */
#[Fillable(['phone', 'code_hash', 'attempts', 'sent_at', 'expires_at'])]
#[Hidden(['code_hash'])]
class CustomerLoginCode extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
