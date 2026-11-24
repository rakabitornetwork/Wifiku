<?php

namespace App\Models;

use App\Enums\VpnProtocol;
use App\Models\Concerns\Audited;
use Database\Factories\VpnServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['router_id', 'name', 'public_host', 'local_address', 'pool_start', 'pool_end', 'ipsec_secret', 'use_ipsec', 'sstp_port', 'port_block_offset', 'l2tp_enabled', 'sstp_enabled', 'is_active', 'setup_at', 'notes'])]
#[Hidden(['ipsec_secret'])]
class VpnServer extends Model
{
    /** @use HasFactory<VpnServerFactory> */
    use Audited, HasFactory;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['router_id', 'name', 'public_host', 'local_address', 'pool_start', 'pool_end', 'use_ipsec', 'sstp_port', 'port_block_offset', 'l2tp_enabled', 'sstp_enabled', 'is_active', 'notes'];

    public static function auditName(): string
    {
        return 'Server VPN';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ipsec_secret' => 'encrypted',
            'use_ipsec' => 'boolean',
            'sstp_port' => 'integer',
            'port_block_offset' => 'integer',
            'l2tp_enabled' => 'boolean',
            'sstp_enabled' => 'boolean',
            'is_active' => 'boolean',
            'setup_at' => 'datetime',
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
     * @return HasMany<VpnAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(VpnAccount::class);
    }

    /**
     * Apakah L2TP di server ini memakai IPsec (butuh IPsec secret).
     */
    public function usesIpsec(): bool
    {
        return $this->l2tp_enabled && $this->use_ipsec;
    }

    /**
     * Apakah $protocol dijual di server ini.
     */
    public function supports(VpnProtocol $protocol): bool
    {
        return $protocol === VpnProtocol::L2tp ? $this->l2tp_enabled : $this->sstp_enabled;
    }
}
