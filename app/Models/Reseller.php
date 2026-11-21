<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\ResellerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'discount_percent', 'is_active'])]
class Reseller extends Model
{
    /** @use HasFactory<ResellerFactory> */
    use Audited, HasFactory;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['name', 'phone', 'discount_percent', 'is_active'];

    public static function auditName(): string
    {
        return 'Reseller';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_percent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<VoucherBatch, $this>
     */
    public function voucherBatches(): HasMany
    {
        return $this->hasMany(VoucherBatch::class);
    }
}
