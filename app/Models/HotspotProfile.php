<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\HotspotProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['router_id', 'name', 'mikrotik_profile', 'price', 'reseller_price', 'validity_minutes', 'uptime_limit_minutes', 'data_limit_mb', 'rate_limit', 'shared_users', 'is_active'])]
class HotspotProfile extends Model
{
    /** @use HasFactory<HotspotProfileFactory> */
    use Audited, HasFactory;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['router_id', 'name', 'mikrotik_profile', 'price', 'reseller_price', 'validity_minutes', 'uptime_limit_minutes', 'data_limit_mb', 'rate_limit', 'shared_users', 'is_active'];

    public static function auditName(): string
    {
        return 'Profil Hotspot';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'reseller_price' => 'integer',
            'validity_minutes' => 'integer',
            'uptime_limit_minutes' => 'integer',
            'data_limit_mb' => 'integer',
            'shared_users' => 'integer',
            'is_active' => 'boolean',
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
     * @return HasMany<VoucherBatch, $this>
     */
    public function voucherBatches(): HasMany
    {
        return $this->hasMany(VoucherBatch::class);
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
