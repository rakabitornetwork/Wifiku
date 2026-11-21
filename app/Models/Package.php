<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['router_id', 'name', 'mikrotik_profile', 'rate_limit', 'price', 'is_active'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use Audited, HasFactory, SoftDeletes;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['router_id', 'name', 'mikrotik_profile', 'rate_limit', 'price', 'is_active'];

    public static function auditName(): string
    {
        return 'Paket';
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
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
