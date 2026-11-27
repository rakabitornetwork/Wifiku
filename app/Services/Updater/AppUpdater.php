<?php

namespace App\Services\Updater;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

/**
 * Pembaruan aplikasi satu klik dari git: periksa commit baru di remote, lalu tarik kode (fast-forward saja), pasang dependensi
 * bila composer.lock berubah, jalankan migrasi, bersihkan cache, dan muat ulang queue worker.
 *
 * Semua perintah memakai daftar argumen tetap (tanpa shell dan tanpa masukan pengguna). Hasil dan log langkah disimpan di
 * storage/app/updater/state.json agar halaman admin bisa menampilkan progres walau prosesnya berjalan di latar belakang.
 */
class AppUpdater
{
    /** Status "berjalan" yang tidak diperbarui selama ini dianggap macet dan boleh diulang. */
    private const STALE_MINUTES = 20;

    private const OUTPUT_LIMIT = 6000;

    /** @var array<string, string> */
    private const STEP_LABELS = [
        'preflight' => 'Memeriksa kondisi repositori',
        'pull' => 'Mengambil kode terbaru (git pull)',
        'composer' => 'Memasang dependensi PHP (composer)',
        'migrate' => 'Menjalankan migrasi database',
        'cache' => 'Membersihkan cache',
        'queue' => 'Memuat ulang queue worker',
    ];

    /**
     * Kondisi versi terpasang dan (setelah `git fetch`) pembaruan yang tersedia.
     *
     * @return array<string, mixed>
     */
    public function check(bool $fetch = true): array
    {
        $fetchError = null;

        if ($fetch) {
            $result = $this->git(['fetch', '--quiet', 'origin'], 60);

            if (! $result->successful()) {
                $fetchError = $this->tail($result->errorOutput() ?: $result->output());
            }
        }

        $upstream = $this->gitLine(['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}']);
        $dirty = $this->gitLine(['status', '--porcelain', '--untracked-files=no']) !== '';
        $incoming = ['behind' => 0, 'ahead' => 0, 'commits' => [], 'files' => []];

        if ($upstream !== '') {
            $incoming['behind'] = (int) $this->gitLine(['rev-list', '--count', 'HEAD..@{u}']);
            $incoming['ahead'] = (int) $this->gitLine(['rev-list', '--count', '@{u}..HEAD']);

            foreach (array_filter(explode("\n", $this->gitLine(['log', 'HEAD..@{u}', '--format=%h%x09%s%x09%cI', '-n', '30'], trim: false))) as $line) {
                [$hash, $subject, $date] = array_pad(explode("\t", $line, 3), 3, '');
                $incoming['commits'][] = ['hash' => $hash, 'subject' => $subject, 'date' => $date];
            }

            $incoming['files'] = array_values(array_filter(explode("\n", $this->gitLine(['diff', '--name-only', 'HEAD', '@{u}'], trim: false))));
        }

        $files = $incoming['files'];
        $blocked = match (true) {
            $upstream === '' => 'Branch ini belum terhubung ke remote, jadi tidak ada yang bisa ditarik.',
            $dirty => 'Ada perubahan lokal yang belum di-commit di server. Commit atau buang perubahan itu lebih dulu agar pembaruan tidak bentrok.',
            $incoming['ahead'] > 0 && $incoming['behind'] > 0 => 'Riwayat git server menyimpang dari remote (ada commit lokal yang tidak ada di remote). Selesaikan lewat terminal.',
            default => null,
        };

        return [
            'current' => [
                'hash' => $this->gitLine(['rev-parse', 'HEAD']),
                'short' => $this->gitLine(['rev-parse', '--short', 'HEAD']),
                'subject' => $this->gitLine(['log', '-1', '--format=%s']),
                'date' => $this->gitLine(['log', '-1', '--format=%cI']),
                'branch' => $this->gitLine(['rev-parse', '--abbrev-ref', 'HEAD']),
                'upstream' => $upstream,
            ],
            'behind' => $incoming['behind'],
            'ahead' => $incoming['ahead'],
            'commits' => $incoming['commits'],
            'file_count' => count($files),
            'needs_composer' => (bool) array_intersect($files, ['composer.json', 'composer.lock']),
            'migration_count' => count(array_filter($files, fn (string $file): bool => str_starts_with($file, 'database/migrations/'))),
            'dirty' => $dirty,
            'fetch_error' => $fetchError,
            'blocked' => $blocked,
            'can_update' => $blocked === null && $fetchError === null && $incoming['behind'] > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $path = $this->statePath();
        $state = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($state)) {
            return ['status' => 'idle', 'steps' => []];
        }

        if (in_array($state['status'], ['queued', 'running'], true) && now()->parse($state['updated_at'] ?? now())->diffInMinutes(now()) >= self::STALE_MINUTES) {
            $state['status'] = 'failed';
            $state['error'] = 'Proses pembaruan berhenti tanpa hasil (kemungkinan terputus). Periksa server lalu coba lagi.';
        }

        return $state;
    }

    public function isBusy(): bool
    {
        return in_array($this->state()['status'], ['queued', 'running'], true);
    }

    /**
     * Tandai antrean pembaruan dan jalankan `php artisan app:update` terlepas dari permintaan web.
     *
     * @throws UpdateException
     */
    public function startInBackground(?string $by): void
    {
        if ($this->isBusy()) {
            throw new UpdateException('Pembaruan sedang berjalan.');
        }

        $check = $this->check();

        if (! $check['can_update']) {
            throw new UpdateException($check['fetch_error'] ?? $check['blocked'] ?? 'Tidak ada pembaruan untuk dipasang.');
        }

        $this->writeState([
            'status' => 'queued',
            'by' => $by,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'from' => $check['current']['short'],
            'to' => null,
            'error' => null,
            'steps' => $this->freshSteps(),
        ]);

        $command = 'nohup '.escapeshellarg($this->phpBinary()).' '.escapeshellarg(base_path('artisan')).' app:update --no-interaction > /dev/null 2>&1 &';
        Process::path(base_path())->timeout(10)->run(['sh', '-c', $command]);
    }

    /**
     * Jalankan seluruh langkah pembaruan (dipanggil oleh `php artisan app:update`). Mengembalikan true bila berhasil.
     */
    public function run(?string $by = null): bool
    {
        $state = $this->state();

        if ($state['status'] === 'running') {
            return false;
        }

        $check = $this->check();

        if ($state['status'] !== 'queued') {
            $state = ['by' => $by, 'started_at' => now()->toIso8601String(), 'from' => $check['current']['short'], 'steps' => $this->freshSteps()];
        }

        $state = [...$state, 'status' => 'running', 'finished_at' => null, 'error' => null];
        $this->writeState($state);
        $previous = $check['current']['hash'];
        $needsComposer = $check['needs_composer'];

        $steps = [
            'preflight' => fn (): array => $check['can_update'] ? [true, 'Siap diperbarui: '.$check['behind'].' commit baru.'] : [false, $check['fetch_error'] ?? $check['blocked'] ?? 'Tidak ada pembaruan untuk dipasang.'],
            'pull' => fn (): array => $this->command($this->git(['pull', '--ff-only'], 120)),
            'composer' => fn (): array => $needsComposer ? $this->composerInstall() : [null, 'composer.lock tidak berubah.'],
            'migrate' => fn (): array => $this->command($this->artisan(['migrate', '--force'])),
            'cache' => fn (): array => $this->command($this->artisan(['optimize:clear'])),
            'queue' => fn (): array => $this->command($this->artisan(['queue:restart'])),
        ];

        foreach ($steps as $key => $step) {
            $this->setStep($state, $key, 'running');

            try {
                [$ok, $output] = $step();
            } catch (Throwable $exception) {
                [$ok, $output] = [false, $exception->getMessage()];
            }

            $this->setStep($state, $key, $ok === null ? 'skipped' : ($ok ? 'done' : 'failed'), $output);

            if ($ok === false) {
                foreach ($state['steps'] as &$pending) {
                    $pending['status'] = $pending['status'] === 'pending' ? 'skipped' : $pending['status'];
                }
                unset($pending);

                $pulled = $key !== 'preflight' && $key !== 'pull';
                $state['error'] = ($pulled ? 'Langkah "'.self::STEP_LABELS[$key].'" gagal setelah kode ditarik. Versi sebelumnya: '.substr($previous, 0, 7).'. ' : 'Langkah "'.self::STEP_LABELS[$key].'" gagal. ').'Detail ada di log langkah.';
                $state['status'] = 'failed';
                $state['finished_at'] = now()->toIso8601String();
                $this->writeState($state);

                return false;
            }
        }

        $state['status'] = 'succeeded';
        $state['to'] = $this->gitLine(['rev-parse', '--short', 'HEAD']);
        $state['finished_at'] = now()->toIso8601String();
        $this->writeState($state);

        return true;
    }

    /**
     * @return list<array{key: string, label: string, status: string, output: string|null}>
     */
    private function freshSteps(): array
    {
        return array_map(fn (string $key): array => ['key' => $key, 'label' => self::STEP_LABELS[$key], 'status' => 'pending', 'output' => null], array_keys(self::STEP_LABELS));
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function command(ProcessResult $result): array
    {
        return [$result->successful(), $this->tail(trim($result->output()."\n".$result->errorOutput()))];
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function composerInstall(): array
    {
        $binary = $this->composerBinary();

        if ($binary === null) {
            return [false, 'Composer tidak ditemukan di server. Pasang composer atau letakkan composer.phar di folder aplikasi.'];
        }

        $args = [...$binary, 'install', '--no-interaction', '--no-progress', '--optimize-autoloader', ...(app()->isProduction() ? ['--no-dev'] : [])];

        return $this->command($this->pending(900)->env(['COMPOSER_HOME' => storage_path('app/updater/composer'), 'COMPOSER_ALLOW_SUPERUSER' => '1'])->run($args));
    }

    /**
     * @return list<string>|null
     */
    private function composerBinary(): ?array
    {
        if (is_file(base_path('composer.phar'))) {
            return [$this->phpBinary(), base_path('composer.phar')];
        }

        $found = trim(Process::timeout(5)->run(['sh', '-c', 'command -v composer'])->output());

        return $found !== '' ? [$found] : null;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function artisan(array $arguments): ProcessResult
    {
        return $this->pending(300)->run([$this->phpBinary(), base_path('artisan'), ...$arguments, '--no-interaction']);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments, int $timeout = 30): ProcessResult
    {
        return $this->pending($timeout)->env(['GIT_TERMINAL_PROMPT' => '0'])->run(['git', ...$arguments]);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function gitLine(array $arguments, bool $trim = true): string
    {
        $output = $this->git($arguments)->output();

        return $trim ? trim($output) : rtrim($output);
    }

    private function pending(int $timeout): PendingProcess
    {
        return Process::path(base_path())->timeout($timeout);
    }

    private function phpBinary(): string
    {
        return (new PhpExecutableFinder)->find() ?: 'php';
    }

    private function tail(string $text): string
    {
        $text = trim($text);

        return mb_strlen($text) > self::OUTPUT_LIMIT ? '…'.mb_substr($text, -self::OUTPUT_LIMIT) : $text;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function setStep(array &$state, string $key, string $status, ?string $output = null): void
    {
        foreach ($state['steps'] as &$step) {
            if ($step['key'] === $key) {
                $step['status'] = $status;
                $step['output'] = $output ?? $step['output'];
            }
        }
        unset($step);

        $this->writeState($state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function writeState(array $state): void
    {
        $path = $this->statePath();
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode([...$state, 'updated_at' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function statePath(): string
    {
        return storage_path('app/updater/state.json');
    }
}
