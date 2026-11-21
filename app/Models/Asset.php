<?php

namespace App\Models;

use App\Enums\AssetOwnership;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Concerns\Audited;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'brand', 'model', 'serial_number', 'mac_address', 'ownership', 'status', 'customer_id', 'genieacs_device_id', 'last_rx_power', 'last_seen_at', 'installed_at', 'notes'])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use Audited, HasFactory;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['type', 'brand', 'model', 'serial_number', 'mac_address', 'ownership', 'status', 'customer_id', 'installed_at'];

    public static function auditName(): string
    {
        return 'Aset';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'ownership' => AssetOwnership::class,
            'status' => AssetStatus::class,
            'last_rx_power' => 'decimal:2',
            'last_seen_at' => 'datetime',
            'installed_at' => 'date:Y-m-d',
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
