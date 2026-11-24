<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\CustomerStatus;
use App\Models\Concerns\Audited;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['customer_code', 'router_id', 'package_id', 'odp_id', 'odp_port', 'name', 'whatsapp', 'address', 'village', 'latitude', 'longitude', 'cable_path', 'pppoe_username', 'pppoe_password', 'billing_cycle', 'billing_day', 'due_days', 'installed_at', 'status', 'promise_to_pay_until', 'isolated_at', 'router_synced_at'])]
#[Hidden(['pppoe_password'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Audited, HasFactory, SoftDeletes;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['customer_code', 'router_id', 'package_id', 'odp_id', 'odp_port', 'name', 'whatsapp', 'address', 'village', 'latitude', 'longitude', 'pppoe_username', 'billing_cycle', 'billing_day', 'due_days', 'installed_at', 'status', 'promise_to_pay_until'];

    public static function auditName(): string
    {
        return 'Pelanggan';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'odp_port' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'cable_path' => 'array',
            'pppoe_password' => 'encrypted',
            'billing_cycle' => BillingCycle::class,
            'billing_day' => 'integer',
            'due_days' => 'integer',
            'installed_at' => 'date',
            'status' => CustomerStatus::class,
            'promise_to_pay_until' => 'date',
            'isolated_at' => 'datetime',
            'router_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsTo<Odp, $this>
     */
    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<WhatsappMessage, $this>
     */
    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    /**
     * @return HasMany<PaymentExtension, $this>
     */
    public function paymentExtensions(): HasMany
    {
        return $this->hasMany(PaymentExtension::class);
    }
}
