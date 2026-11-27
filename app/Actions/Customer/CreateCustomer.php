<?php

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Jobs\Network\SyncPppoeSecretJob;
use App\Models\Customer;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateCustomer
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly GenerateCustomerCode $customerCode) {}

    /**
     * Ucapan selamat datang untuk pelanggan aktif; kegagalan kirim tidak boleh menggagalkan pendaftaran.
     */
    private function welcome(Customer $customer): void
    {
        if ($customer->status !== CustomerStatus::Active) {
            return;
        }

        try {
            app(CustomerNotifier::class)->welcome($customer);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Simpan pelanggan baru dengan ID pelanggan otomatis (TT-YYMM-0001).
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Customer
    {
        if (($data['status'] ?? null) === CustomerStatus::Isolated->value) {
            $data['isolated_at'] = now();
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                $customer = Customer::create([...$data, 'customer_code' => $this->customerCode->handle()]);

                SyncPppoeSecretJob::dispatch($customer);
                $this->welcome($customer);

                return $customer;
            } catch (UniqueConstraintViolationException $exception) {
                // Dua pelanggan dibuat bersamaan mendapat nomor yang sama; ulangi dengan nomor berikutnya.
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }
}
