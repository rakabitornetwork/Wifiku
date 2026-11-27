<?php

namespace App\Console\Commands;

use App\Services\Updater\AppUpdater;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:update')]
#[Description('Perbarui aplikasi dari git: tarik kode, pasang dependensi, migrasi, bersihkan cache, dan muat ulang queue worker')]
class UpdateApplication extends Command
{
    public function handle(AppUpdater $updater): int
    {
        if ($updater->state()['status'] === 'running') {
            $this->error('Pembaruan lain sedang berjalan.');

            return self::FAILURE;
        }

        $ok = $updater->run('terminal');

        foreach ($updater->state()['steps'] as $step) {
            $this->line(sprintf('[%s] %s', $step['status'], $step['label']));
        }

        $ok ? $this->info('Pembaruan selesai.') : $this->error($updater->state()['error'] ?? 'Pembaruan gagal.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
