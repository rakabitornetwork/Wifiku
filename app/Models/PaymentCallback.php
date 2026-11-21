<?php

namespace App\Models;

use App\Enums\PaymentGatewayDriver;
use Database\Factories\PaymentCallbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['driver', 'merchant_ref', 'signature_valid', 'payload', 'ip_address', 'processed_at', 'error'])]
class PaymentCallback extends Model
{
    /** @use HasFactory<PaymentCallbackFactory> */
    use HasFactory;

    /**
     * Log callback hanya ditulis sekali, tidak pernah diperbarui.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => PaymentGatewayDriver::class,
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
