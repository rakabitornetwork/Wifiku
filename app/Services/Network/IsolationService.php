<?php

namespace App\Services\Network;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Whatsapp\CustomerNotifier;
use Carbon\CarbonImmutable;

/**
 * Logika Auto-Isolir, Janji Bayar, dan Auto-Open pelanggan PPPoE.
 * Router diubah lebih dulu; status di database baru diperbarui setelah router berhasil,
 * sehingga kegagalan router membuat job dicoba ulang tanpa data yang menyimpang.
 */
class IsolationService
{
    public function __construct(private readonly CustomerNotifier $notifier) {}

    /**
     * Ada invoice belum lunas yang jatuh temponya sudah lewat.
     */
    public function hasOverdueInvoices(Customer $customer, ?CarbonImmutable $today = null): bool
    {
        return $customer->invoices()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->where('due_date', '<', ($today ?? CarbonImmutable::today())->startOfDay())
            ->exists();
    }

    /**
     * Pelanggan masih dalam masa Janji Bayar (sampai dan termasuk tanggal janji).
     */
    public function isUnderPromise(Customer $customer, ?CarbonImmutable $today = null): bool
    {
        return $customer->promise_to_pay_until !== null
            && CarbonImmutable::parse($customer->promise_to_pay_until)->startOfDay()->greaterThanOrEqualTo(($today ?? CarbonImmutable::today())->startOfDay());
    }

    /**
     * Apakah pelanggan wajib diisolir (aktif, lewat jatuh tempo, dan tidak dalam masa Janji Bayar).
     */
    public function shouldIsolate(Customer $customer, ?CarbonImmutable $today = null): bool
    {
        return $customer->status === CustomerStatus::Active
            && ! $customer->trashed()
            && ! $this->isUnderPromise($customer, $today)
            && $this->hasOverdueInvoices($customer, $today);
    }

    /**
     * Apakah pelanggan terisolir boleh dibuka (lunas semua tunggakan atau sedang Janji Bayar).
     */
    public function canReopen(Customer $customer, ?CarbonImmutable $today = null): bool
    {
        return $customer->status === CustomerStatus::Isolated
            && ! $customer->trashed()
            && ($this->isUnderPromise($customer, $today) || ! $this->hasOverdueInvoices($customer, $today));
    }

    /**
     * Pindahkan pelanggan ke profile isolir router.
     */
    public function isolate(Customer $customer): void
    {
        $customer->loadMissing('router');

        MikrotikService::forRouter($customer->router)->setPppoeProfile($customer, $customer->router->isolir_profile);

        $customer->update(['status' => CustomerStatus::Isolated, 'isolated_at' => now()]);

        $this->notifier->isolated($customer);
    }

    /**
     * Kembalikan pelanggan ke profile paket normal.
     */
    public function reopen(Customer $customer): void
    {
        $customer->loadMissing(['router', 'package']);

        MikrotikService::forRouter($customer->router)->setPppoeProfile($customer, $customer->package->mikrotik_profile);

        $customer->update(['status' => CustomerStatus::Active, 'isolated_at' => null]);

        $this->notifier->reopened($customer);
    }
}
