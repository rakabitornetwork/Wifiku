<?php

namespace App\Models;

use App\Enums\RouterStatus;
use App\Models\Concerns\Audited;
use Database\Factories\RouterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['name', 'host', 'api_port', 'use_ssl', 'username', 'password', 'isolir_profile', 'webhook_token', 'status', 'last_checked_at', 'last_error', 'notes'])]
#[Hidden(['password', 'webhook_token'])]
class Router extends Model
{
    /** @use HasFactory<RouterFactory> */
    use Audited, HasFactory, SoftDeletes;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['name', 'host', 'api_port', 'use_ssl', 'username', 'isolir_profile', 'notes'];

    public static function auditName(): string
    {
        return 'Router';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'use_ssl' => 'boolean',
            'password' => 'encrypted',
            'webhook_token' => 'encrypted',
            'status' => RouterStatus::class,
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Odp, $this>
     */
    public function odps(): HasMany
    {
        return $this->hasMany(Odp::class);
    }

    /**
     * Penanda baris webhook di dalam script on-login, agar tidak ditambahkan dua kali.
     */
    public const HOTSPOT_SCRIPT_MARKER = '# wifiku-webhook';

    /**
     * Script on-login Hotspot User Profile yang memanggil webhook login pertama voucher.
     * Token webhook dibuat bila router belum punya.
     */
    public function hotspotLoginScript(): string
    {
        if (blank($this->webhook_token)) {
            $this->forceFill(['webhook_token' => Str::random(40)])->save();
        }

        $url = route('webhooks.hotspot-login', $this).'?token='.$this->webhook_token;

        return ':do { /tool fetch url=("'.$url.'&user=" . $user . "&mac=" . $"mac-address") keep-result=no check-certificate=no } on-error={}';
    }

    /**
     * Apakah $profile adalah profile isolir router ini (huruf besar/kecil diabaikan: "isolir" = "ISOLIR").
     */
    public function isIsolirProfile(?string $profile): bool
    {
        return $profile !== null && strcasecmp(trim($profile), trim($this->isolir_profile)) === 0;
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @return HasMany<HotspotProfile, $this>
     */
    public function hotspotProfiles(): HasMany
    {
        return $this->hasMany(HotspotProfile::class);
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

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
