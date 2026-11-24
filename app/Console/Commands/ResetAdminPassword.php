<?php

namespace App\Console\Commands;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Jalur cadangan bila admin terkunci (tidak punya nomor WhatsApp dan email belum dikonfigurasi): hanya bisa dijalankan dari server.
 */
class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {email : Email akun admin} {--generate : Buat password acak dan tampilkan sekali}';

    protected $description = 'Atur ulang password pengguna panel admin dari server';

    public function handle(): int
    {
        $user = User::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('Pengguna dengan email itu tidak ditemukan.');

            return self::FAILURE;
        }

        if ($this->option('generate')) {
            $password = Str::password(16, symbols: false);
        } else {
            $password = (string) $this->secret('Password baru (minimal 8 karakter)');

            if (strlen($password) < 8) {
                $this->error('Password minimal 8 karakter.');

                return self::FAILURE;
            }

            if ($password !== (string) $this->secret('Ulangi password baru')) {
                $this->error('Konfirmasi password tidak sama.');

                return self::FAILURE;
            }
        }

        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        PasswordResetController::endSessions($user);

        activity('Pengguna')->performedOn($user)->log('Password direset lewat perintah server');

        $this->info("Password {$user->email} diubah dan semua sesi loginnya diakhiri.");

        if ($this->option('generate')) {
            $this->line("Password baru: {$password}");
            $this->warn('Catat sekarang dan segera ganti setelah masuk; password ini tidak ditampilkan lagi.');
        }

        if (! $user->is_active) {
            $this->warn('Akun ini masih dinonaktifkan; aktifkan dari halaman Pengguna agar bisa masuk.');
        }

        return self::SUCCESS;
    }
}
