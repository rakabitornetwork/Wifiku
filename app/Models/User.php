<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Audited;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'avatar_path', 'password', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Audited, HasFactory, HasRoles, Notifiable;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['name', 'email', 'phone', 'is_active'];

    public static function auditName(): string
    {
        return 'Pengguna';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Foto profil ikut terhapus bersama penggunanya.
        static::deleted(function (User $user): void {
            if (filled($user->avatar_path)) {
                Storage::disk('local')->delete($user->avatar_path);
            }
        });
    }

    /**
     * URL foto profil (disajikan lewat route yang butuh login); null bila belum ada.
     * Parameter v berubah setiap foto diganti agar cache browser ikut diperbarui.
     */
    public function avatarUrl(): ?string
    {
        return filled($this->avatar_path)
            ? route('avatar.show', ['user' => $this->id, 'v' => substr(md5($this->avatar_path), 0, 8)])
            : null;
    }

    /**
     * @return HasMany<VoucherBatch, $this>
     */
    public function voucherBatchesCreated(): HasMany
    {
        return $this->hasMany(VoucherBatch::class, 'created_by');
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expensesCreated(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function paymentsReceived(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }
}
