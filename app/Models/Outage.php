<?php

namespace App\Models;

use App\Enums\OutageStatus;
use App\Models\Concerns\Audited;
use Database\Factories\OutageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['odp_id', 'title', 'message', 'status', 'notified_count', 'created_by', 'resolved_at'])]
class Outage extends Model
{
    /** @use HasFactory<OutageFactory> */
    use Audited, HasFactory;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['odp_id', 'title', 'status', 'resolved_at'];

    public static function auditName(): string
    {
        return 'Gangguan';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OutageStatus::class,
            'notified_count' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Odp, $this>
     */
    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
