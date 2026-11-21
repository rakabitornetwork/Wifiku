<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['voucher_batch_id', 'router_id', 'hotspot_profile_id', 'code', 'password', 'price', 'status', 'synced_to_router', 'first_login_at', 'expires_at', 'mac_address'])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'status' => VoucherStatus::class,
            'synced_to_router' => 'boolean',
            'first_login_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<VoucherBatch, $this>
     */
    public function voucherBatch(): BelongsTo
    {
        return $this->belongsTo(VoucherBatch::class);
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * @return BelongsTo<HotspotProfile, $this>
     */
    public function hotspotProfile(): BelongsTo
    {
        return $this->belongsTo(HotspotProfile::class);
    }
}
