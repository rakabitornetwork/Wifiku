<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyDatabaseImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('legacy:import {path : File SQLite aplikasi lama} {--old-key= : APP_KEY aplikasi lama (kosongkan untuk diminta)} {--old-previous-key=* : APP_KEY lama sebelumnya (APP_PREVIOUS_KEYS), boleh diulang} {--old-cipher=AES-256-CBC} {--old-timezone=UTC : Zona waktu timestamp di database lama} {--dry-run : Jalankan lalu batalkan semua perubahan}')]
#[Description('Impor router, paket, pelanggan PPPoE, tagihan, dan pembayaran dari database aplikasi lama')]
class ImportLegacyDatabase extends Command
{
    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File database tidak ditemukan atau tidak bisa dibaca: {$path}");

            return self::FAILURE;
        }

        $key = (string) ($this->option('old-key') ?: $this->secret('APP_KEY aplikasi lama (base64:...)'));

        try {
            $decode = fn (string $value): string => str_starts_with($value, 'base64:') ? base64_decode(substr($value, 7)) : $value;
            $encrypter = new Encrypter($decode($key), (string) $this->option('old-cipher'));
            $encrypter->previousKeys(array_map($decode, (array) $this->option('old-previous-key')));
        } catch (Throwable $exception) {
            $this->error("APP_KEY lama tidak valid: {$exception->getMessage()}");

            return self::FAILURE;
        }

        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => realpath($path), 'foreign_key_constraints' => false]]);

        DB::beginTransaction();

        try {
            $report = (new LegacyDatabaseImporter(DB::connection('legacy'), $encrypter, (string) $this->option('old-timezone')))->import();
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
