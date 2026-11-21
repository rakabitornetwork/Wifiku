<?php

namespace App\Models;

use App\Enums\FeeBearer;
use App\Enums\PaymentGatewayDriver;
use App\Models\Concerns\Audited;
use Database\Factories\PaymentGatewayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['driver', 'name', 'credentials', 'is_active', 'is_sandbox', 'enabled_channels', 'fee_bearer', 'sort_order'])]
#[Hidden(['credentials'])]
class PaymentGateway extends Model
{
    /** @use HasFactory<PaymentGatewayFactory> */
    use Audited, HasFactory;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['name', 'is_active', 'is_sandbox', 'enabled_channels', 'fee_bearer', 'sort_order'];

    public static function auditName(): string
    {
        return 'Payment Gateway';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => PaymentGatewayDriver::class,
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
            'enabled_channels' => 'array',
            'fee_bearer' => FeeBearer::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
