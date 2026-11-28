<?php

namespace App\Services\Billing;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Whatsapp\CustomerNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Membuat invoice siklus berjalan untuk pelanggan, termasuk prorata bulan pertama.
 *
 * Siklus tagihan dimulai pada billing_day dan berakhir sehari sebelum billing_day bulan berikutnya.
 * Invoice pertama dihitung prorata bila tanggal pasang jatuh setelah awal siklus.
 * Satu siklus hanya punya satu invoice (unik pada customer_id + period_end), jadi aman dijalankan berulang.
 */
class InvoiceGeneratorService
{
    private const MAX_NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly ProrataCalculator $prorata,
        private readonly CustomerNotifier $notifier,
    ) {}

    /**
     * Siklus tagihan yang memuat $date untuk tanggal tagihan $billingDay (1–31).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function cycleContaining(int $billingDay, CarbonImmutable $date): array
    {
        return $this->prorata->cycleContaining($billingDay, $date);
    }

    /**
     * Buat invoice siklus yang memuat $today; kembalikan invoice yang sudah ada bila siklus itu sudah ditagih.
     * Mengembalikan null bila pelanggan tidak perlu ditagih (nonaktif, belum terpasang, atau paketnya gratis/Rp 0).
     */
    public function generateFor(Customer $customer, CarbonImmutable $today): ?Invoice
    {
        $today = $today->startOfDay();

        if ($customer->trashed() || $customer->status === CustomerStatus::Inactive) {
            return null;
        }

        $installedAt = CarbonImmutable::parse($customer->installed_at)->startOfDay();

        if ($installedAt->greaterThan($today)) {
            return null;
        }

        $customer->loadMissing('package');

        // Paket Rp 0 (akun internal: server, tunnel, self-service) tidak ditagih; tagihan Rp 0 tak bisa dibayar sehingga
        // pelanggannya akan tertunggak selamanya dan ikut diisolir.
        if ($customer->package->price <= 0) {
            return null;
        }

        [$cycleStart, $cycleEnd] = $this->cycleContaining($customer->billing_day, $today);

        if ($existing = $this->existingFor($customer, $cycleEnd)) {
            return $existing;
        }

        $serviceStart = $installedAt->max($cycleStart);
        $isProrated = $serviceStart->greaterThan($cycleStart);
        $price = $customer->package->price;

        $prorata = $isProrated ? $this->prorata->calculate($price, $serviceStart, $cycleStart, $cycleEnd) : null;
        $total = $prorata['amount'] ?? $price;

        for ($attempt = 1; ; $attempt++) {
            try {
                $invoice = DB::transaction(fn () => $this->createInvoice($customer, $today, $serviceStart, $cycleEnd, $total, $prorata));

                break;
            } catch (UniqueConstraintViolationException $exception) {
                // Proses lain sudah menagih siklus ini, atau nomor invoice bentrok: pakai yang ada / coba nomor berikutnya.
                if ($existing = $this->existingFor($customer, $cycleEnd)) {
                    return $existing;
                }

                if ($attempt >= self::MAX_NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        $this->notifier->invoiceIssued($invoice->setRelation('customer', $customer));

        return $invoice;
    }

    /**
     * @param  array{amount: int, days: int, period_days: int}|null  $prorata
     */
    private function createInvoice(Customer $customer, CarbonImmutable $today, CarbonImmutable $serviceStart, CarbonImmutable $cycleEnd, int $total, ?array $prorata): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => $this->nextNumber($today),
            'customer_id' => $customer->id,
            'period_start' => $serviceStart,
            'period_end' => $cycleEnd,
            'issue_date' => $today,
            'due_date' => $today->addDays($customer->due_days),
            'is_prorated' => $prorata !== null,
            'prorata_days' => $prorata['days'] ?? null,
            'period_days' => $prorata['period_days'] ?? null,
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $period = $serviceStart->format('d/m/Y').' – '.$cycleEnd->format('d/m/Y');

        $invoice->items()->create([
            'type' => $prorata ? InvoiceItemType::Prorata : InvoiceItemType::Package,
            'description' => $prorata
                ? "{$customer->package->name} (prorata {$prorata['days']}/{$prorata['period_days']} hari, {$period})"
                : "{$customer->package->name} ({$period})",
            'quantity' => 1,
            'unit_price' => $total,
            'amount' => $total,
        ]);

        return $invoice;
    }

    private function existingFor(Customer $customer, CarbonImmutable $cycleEnd): ?Invoice
    {
        return Invoice::withTrashed()->where('customer_id', $customer->id)->where('period_end', $cycleEnd)->first();
    }

    /**
     * Nomor invoice berikutnya bulan ini (dipakai juga oleh tagihan VPN).
     */
    public function nextNumber(CarbonImmutable $today): string
    {
        $prefix = 'INV/'.$today->format('ym').'/';
        $last = Invoice::withTrashed()->where('invoice_number', 'like', $prefix.'%')->max('invoice_number');
        $sequence = $last === null ? 1 : (int) substr($last, -5) + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
