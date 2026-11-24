<?php

namespace App\Models;

use Database\Factories\PaymentExtensionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perpanjangan / Janji Bayar yang diberikan kepada pelanggan.
 */
#[Fillable(['customer_id', 'previous_until', 'granted_until', 'days', 'reason', 'granted_by'])]
class PaymentExtension extends Model
{
    /** @use HasFactory<PaymentExtensionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_until' => 'date:Y-m-d',
            'granted_until' => 'date:Y-m-d',
            'days' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
