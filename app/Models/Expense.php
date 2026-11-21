<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['expense_category_id', 'router_id', 'amount', 'expense_date', 'description', 'receipt_path', 'created_by'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use Audited, HasFactory, SoftDeletes;

    /**
     * Atribut yang dicatat di Audit Log (tanpa rahasia).
     *
     * @var list<string>
     */
    protected array $audited = ['expense_category_id', 'router_id', 'amount', 'expense_date', 'description', 'receipt_path'];

    public static function auditName(): string
    {
        return 'Pengeluaran';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expense_date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
