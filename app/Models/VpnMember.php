<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\VpnMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'email', 'whatsapp', 'password', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class VpnMember extends Authenticatable
{
    /** @use HasFactory<VpnMemberFactory> */
    use Audited, HasFactory;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['name', 'email', 'whatsapp', 'is_active'];

    public static function auditName(): string
    {
        return 'Member VPN';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<VpnAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(VpnAccount::class);
    }
}
