<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\OdpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['router_id', 'parent_odp_id', 'name', 'code', 'latitude', 'longitude', 'total_ports', 'description'])]
class Odp extends Model
{
    /** @use HasFactory<OdpFactory> */
    use Audited, HasFactory;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['router_id', 'parent_odp_id', 'name', 'code', 'latitude', 'longitude', 'total_ports', 'description'];

    public static function auditName(): string
    {
        return 'ODP';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'total_ports' => 'integer',
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
     * @return BelongsTo<Odp, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Odp::class, 'parent_odp_id');
    }

    /**
     * @return HasMany<Odp, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Odp::class, 'parent_odp_id');
    }

    /**
     * @return HasMany<Outage, $this>
     */
    public function outages(): HasMany
    {
        return $this->hasMany(Outage::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
