<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\Audited;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['invoice_number', 'customer_id', 'vpn_account_id', 'period_start', 'period_end', 'issue_date', 'due_date', 'is_prorated', 'prorata_days', 'period_days', 'subtotal', 'discount', 'tax', 'total', 'amount_paid', 'status', 'paid_at', 'reminder_sent_at', 'due_reminder_sent_at', 'notes'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Audited, HasFactory, SoftDeletes;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['invoice_number', 'customer_id', 'vpn_account_id', 'due_date', 'discount', 'tax', 'total', 'amount_paid', 'status', 'notes'];

    /**
     * Invoice dibuat massal oleh sistem; hanya perubahan sesudahnya yang dicatat.
     *
     * @var list<string>
     */
    protected static array $doNotRecordEvents = ['created'];

    public static function auditName(): string
    {
        return 'Invoice';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'is_prorated' => 'boolean',
            'prorata_days' => 'integer',
            'period_days' => 'integer',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'due_reminder_sent_at' => 'datetime',
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
     * @return HasMany<InvoiceItem, $this>
     */
    /**
     * @return BelongsTo<VpnAccount, $this>
     */
    public function vpnAccount(): BelongsTo
    {
        return $this->belongsTo(VpnAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
