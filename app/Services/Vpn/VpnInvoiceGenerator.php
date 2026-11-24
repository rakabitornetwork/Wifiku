<?php

namespace App\Services\Vpn;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\VpnAccountStatus;
use App\Models\Invoice;
use App\Models\VpnAccount;
use App\Services\Billing\InvoiceGeneratorService;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\CustomerNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Menerbitkan tagihan perpanjangan satu bulan untuk akun VPN menjelang masa berlakunya habis.
 *
 * Periode tagihan dimulai sehari setelah `expires_at` (atau hari ini bila akun sudah kedaluwarsa)
 * dan berlangsung satu bulan. Selama sudah ada invoice yang periodenya melewati `expires_at`,
 * akun tidak ditagih lagi, jadi aman dijalankan berulang.
 */
class VpnInvoiceGenerator
{
    private const MAX_NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly InvoiceGeneratorService $numbers,
        private readonly SettingService $settings,
        private readonly CustomerNotifier $notifier,
    ) {}

    /**
     * Apakah akun ini perlu ditagih pada $today.
     */
    public function isDue(VpnAccount $account, CarbonImmutable $today): bool
    {
        if ($account->expires_at === null || $account->monthly_price <= 0) {
            return false;
        }

        // Akun yang dinonaktifkan manual tidak ditagih; yang mati karena kedaluwarsa tetap ditagih agar bisa dibuka lagi.
        if ($account->status === VpnAccountStatus::Disabled && $account->auto_disabled_at === null) {
            return false;
        }

        $expiresAt = CarbonImmutable::parse($account->expires_at)->startOfDay();
        $leadDays = (int) $this->settings->get('billing', 'vpn_invoice_days_before');

        if ($expiresAt->greaterThan($today->addDays($leadDays))) {
            return false;
        }

        return ! $account->invoices()
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->where('period_end', '>', $expiresAt)
            ->exists();
    }

    /**
     * Terbitkan invoice perpanjangan bila akun jatuh tempo ditagih; null bila belum perlu.
     */
    public function generateFor(VpnAccount $account, CarbonImmutable $today): ?Invoice
    {
        $today = $today->startOfDay();

        if (! $this->isDue($account, $today)) {
            return null;
        }

        $expiresAt = CarbonImmutable::parse($account->expires_at)->startOfDay();
        $periodStart = $expiresAt->greaterThanOrEqualTo($today) ? $expiresAt->addDay() : $today;
        $periodEnd = $periodStart->addMonthNoOverflow()->subDay();
        // Jatuh tempo = hari terakhir masa berlaku; akun yang sudah kedaluwarsa dibayar hari ini juga.
        $dueDate = $expiresAt->greaterThan($today) ? $expiresAt : $today;

        for ($attempt = 1; ; $attempt++) {
            try {
                $invoice = DB::transaction(fn () => $this->createInvoice($account, $today, $periodStart, $periodEnd, $dueDate));

                break;
            } catch (UniqueConstraintViolationException $exception) {
                // Proses lain sudah menagih periode ini, atau nomor invoice bentrok.
                if ($existing = $account->invoices()->where('period_start', $periodStart)->first()) {
                    return $existing;
                }

                if ($attempt >= self::MAX_NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        $this->notifier->invoiceIssued($invoice->setRelation('vpnAccount', $account));

        return $invoice;
    }

    /**
     * Invoice aktivasi untuk akun buatan member (bulan pertama), jatuh tempo besok sesuai masa berlaku QRIS/VA.
     */
    public function issueInitial(VpnAccount $account, CarbonImmutable $today): Invoice
    {
        $today = $today->startOfDay();
        $periodEnd = $today->addMonthNoOverflow()->subDay();

        for ($attempt = 1; ; $attempt++) {
            try {
                $invoice = DB::transaction(fn () => $this->createInvoice($account, $today, $today, $periodEnd, $today->addDay()));

                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::MAX_NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        $this->notifier->invoiceIssued($invoice->setRelation('vpnAccount', $account));

        return $invoice;
    }

    private function createInvoice(VpnAccount $account, CarbonImmutable $today, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, CarbonImmutable $dueDate): Invoice
    {
        $total = $account->monthly_price;

        $invoice = Invoice::create([
            'invoice_number' => $this->numbers->nextNumber($today),
            'vpn_account_id' => $account->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'issue_date' => $today,
            'due_date' => $dueDate,
            'is_prorated' => false,
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $invoice->items()->create([
            'type' => InvoiceItemType::Vpn,
            'description' => "VPN Tunnel {$account->username} ({$account->protocol->label()}, {$periodStart->format('d/m/Y')} – {$periodEnd->format('d/m/Y')})",
            'quantity' => 1,
            'unit_price' => $total,
            'amount' => $total,
        ]);

        return $invoice;
    }
}
