<?php

namespace App\Services\Portal;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\CustomerLoginCode;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Login portal pelanggan tanpa password: kode OTP 6 digit dikirim ke nomor WhatsApp yang tersimpan pada data pelanggan.
 * Hasil permintaan sengaja tidak membedakan kode pelanggan yang ada dan tidak ada agar kode tidak bisa ditebak.
 */
class CustomerLoginService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public function __construct(private readonly CustomerNotifier $notifier) {}

    public function normalize(string $customerCode): string
    {
        return Str::upper(trim($customerCode));
    }

    /**
     * Pelanggan yang boleh login: bukan nonaktif dan punya nomor WhatsApp untuk menerima kode.
     */
    public function findEligible(string $customerCode): ?Customer
    {
        $customer = Customer::query()->whereRaw('UPPER(customer_code) = ?', [$this->normalize($customerCode)])->first();

        if ($customer === null || $customer->status === CustomerStatus::Inactive || blank($customer->whatsapp)) {
            return null;
        }

        return $customer;
    }

    /**
     * Kirim kode baru bila pelanggan ada dan jeda kirim ulang sudah lewat; selain itu tidak melakukan apa pun.
     */
    public function send(string $customerCode): void
    {
        $customer = $this->findEligible($customerCode);

        if ($customer === null) {
            return;
        }

        $pending = CustomerLoginCode::query()->where('customer_id', $customer->id)->first();

        if ($pending !== null && $pending->sent_at->diffInSeconds(now(), true) < self::RESEND_SECONDS) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        CustomerLoginCode::query()->updateOrCreate(['customer_id' => $customer->id], [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => now(),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->notifier->loginCode($customer, $code, self::TTL_MINUTES);
    }

    /**
     * Periksa kode; mengembalikan pelanggan bila benar. Kode dipakai sekali, dan dibatalkan setelah MAX_ATTEMPTS salah.
     */
    public function verify(string $customerCode, string $code): ?Customer
    {
        $customer = $this->findEligible($customerCode);
        $pending = $customer === null ? null : CustomerLoginCode::query()->where('customer_id', $customer->id)->first();

        if ($customer === null || $pending === null) {
            return null;
        }

        if ($pending->expires_at->isPast()) {
            $pending->delete();

            return null;
        }

        if (! Hash::check(trim($code), $pending->code_hash)) {
            $pending->increment('attempts');

            if ($pending->attempts >= self::MAX_ATTEMPTS) {
                $pending->delete();
            }

            return null;
        }

        $pending->delete();

        return $customer;
    }
}
