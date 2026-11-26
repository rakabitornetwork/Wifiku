<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyBillingContactSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('legacy:sync-billing-contacts {path : File SQLite aplikasi lama} {--dry-run : Jalankan lalu batalkan semua perubahan}')]
#[Description('Salin tanggal tagihan dan nomor WhatsApp pelanggan dari database aplikasi lama (dicocokkan lewat username PPPoE)')]
class SyncLegacyBillingContacts extends Command
{
    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File database tidak ditemukan atau tidak bisa dibaca: {$path}");

            return self::FAILURE;
        }

        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => realpath($path), 'foreign_key_constraints' => false]]);

        DB::beginTransaction();

        try {
            $report = (new LegacyBillingContactSync(DB::connection('legacy')))->sync();
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->warn('Dry-run: tidak ada perubahan yang disimpan.');
        } else {
            DB::commit();
        }

        $this->table(['Item', 'Jumlah'], collect($report)->map(fn (int $count, string $item): array => [$item, $count])->values()->all());

        return self::SUCCESS;
    }
}
