<?php

namespace App\Models;

use App\Enums\VpnAccountStatus;
use App\Enums\VpnProtocol;
use App\Models\Concerns\Audited;
use Database\Factories\VpnAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['vpn_server_id', 'name', 'whatsapp', 'protocol', 'username', 'password', 'remote_address', 'forwards', 'status', 'adopted', 'auto_disabled_at', 'expires_at', 'monthly_price', 'synced_at', 'last_error', 'notes'])]
#[Hidden(['password'])]
class VpnAccount extends Model
{
    /** @use HasFactory<VpnAccountFactory> */
    use Audited, HasFactory;

    /**
     * Atribut yang dicatat di Audit Log (tanpa password).
     *
     * @var list<string>
     */
    protected array $audited = ['vpn_server_id', 'name', 'whatsapp', 'protocol', 'username', 'remote_address', 'forwards', 'status', 'expires_at', 'monthly_price', 'notes'];

    protected static function booted(): void
    {
        static::creating(function (VpnAccount $account): void {
            $account->portal_token ??= Str::random(40);
        });
    }

    public static function auditName(): string
    {
        return 'Akun VPN';
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'protocol' => VpnProtocol::class,
            'status' => VpnAccountStatus::class,
            'adopted' => 'boolean',
            'auto_disabled_at' => 'datetime',
            'password' => 'encrypted',
            'forwards' => 'array',
            'expires_at' => 'date:Y-m-d',
            'monthly_price' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<VpnServer, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(VpnServer::class, 'vpn_server_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Akun yang lewat tanggal berakhir dianggap kedaluwarsa walau statusnya belum diubah.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->endOfDay()->isPast();
    }
}
