<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Merekam perubahan model ke Audit Log. Model menentukan atribut yang dicatat lewat $audited;
 * kredensial dan rahasia tidak boleh masuk daftar itu.
 *
 * @property list<string> $audited
 */
trait Audited
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(static::auditName())
            ->logOnly($this->audited)
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event): string => match ($event) {
                'created' => static::auditName().' dibuat',
                'updated' => static::auditName().' diubah',
                'deleted' => static::auditName().' dihapus',
                'restored' => static::auditName().' dipulihkan',
                default => static::auditName()." {$event}",
            });
    }

    /**
     * Nama jenis data di Audit Log, mis. "Pelanggan".
     */
    abstract public static function auditName(): string;
}
