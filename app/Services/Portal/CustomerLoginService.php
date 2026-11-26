<?php

namespace App\Services\Portal;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\CustomerLoginCode;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Login portal pelanggan tanpa password: kode OTP 6 digit dikirim ke nomor WhatsApp yang dimasukkan, dan hanya
 * bila nomor itu terdaftar pada data pelanggan. Hasil permintaan sengaja tidak membedakan nomor yang terdaftar dan tidak
 * agar daftar pelanggan tidak bisa ditelusuri. Satu nomor boleh dipakai beberapa sambungan.
 */
class CustomerLoginService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const MAX_CODES_PER_HOUR = 5;

    public function __construct(private readonly CustomerNotifier $notifier) {}

    /**
     * "0812-3456-7890", "+62 812 3456 7890", dan "812..." menjadi "6281234567890"; null bila bukan nomor yang masuk akal.
     */
    public static function normalizePhone(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        $phone = match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => '',
        };

        return preg_match('/^62\d{8,13}$/', $phone) === 1 ? $phone : null;
    }

    /**
     * "6281234567890" menjadi "0812****7890" untuk ditampilkan kembali kepada pengunjung.
     */
    public static function mask(string $phone): string
    {
        $local = '0'.substr($phone, 2);

        return substr($local, 0, 4).str_repeat('*', max(0, strlen($local) - 8)).substr($local, -4);
    }

    /**
     * Pelanggan yang boleh login dengan nomor ini: bukan nonaktif, nomor WhatsApp-nya sama setelah dinormalkan.
     *
     * @return Collection<int, Customer>
     */
    public function customersFor(string $phone): Collection
    {
        $tail = substr($phone, -8);

        return Customer::query()
            ->with('package:id,name')
            ->where('status', '!=', CustomerStatus::Inactive)
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(whatsapp, '-', ''), ' ', ''), '+', ''), '.', '') LIKE ?", ['%'.$tail])
            ->orderBy('name')
            ->get()
            ->filter(fn (Customer $customer): bool => self::normalizePhone($customer->whatsapp) === $phone)
            ->values();
    }

    /**
     * Kirim kode baru bila nomor terdaftar, jeda kirim ulang sudah lewat, dan batas per jam belum habis; selain itu diam.
     */
    public function send(string $phone): void
    {
        if ($this->customersFor($phone)->isEmpty()) {
            return;
        }

        $pending = CustomerLoginCode::query()->where('phone', $phone)->first();

        if ($pending !== null && $pending->sent_at->diffInSeconds(now(), true) < self::RESEND_SECONDS) {
            return;
        }

        if (! RateLimiter::attempt('portal-otp:'.$phone, self::MAX_CODES_PER_HOUR, fn (): bool => true, 3600)) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        CustomerLoginCode::query()->updateOrCreate(['phone' => $phone], [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => now(),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->notifier->loginCode($phone, $code, self::TTL_MINUTES);
    }

    /**
     * Periksa kode untuk nomor ini. Kode dipakai sekali, dan dibatalkan setelah MAX_ATTEMPTS salah.
     */
    public function verify(string $phone, string $code): bool
    {
        $pending = CustomerLoginCode::query()->where('phone', $phone)->first();

        if ($pending === null) {
            return false;
        }

        if ($pending->expires_at->isPast()) {
            $pending->delete();

            return false;
        }

        if (! Hash::check(trim($code), $pending->code_hash)) {
            $pending->increment('attempts');

            if ($pending->attempts >= self::MAX_ATTEMPTS) {
                $pending->delete();
            }

            return false;
        }

        $pending->delete();

        return $this->customersFor($phone)->isNotEmpty();
    }
}
