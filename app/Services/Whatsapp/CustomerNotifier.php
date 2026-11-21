<?php

namespace App\Services\Whatsapp;

use App\Enums\InvoiceStatus;
use App\Enums\WhatsappMessageType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\Payment;
use App\Services\Settings\SettingService;
use Carbon\CarbonInterface;

/**
 * Template pesan WhatsApp untuk pelanggan: invoice, pengingat H-3, bukti bayar, isolir, dan aktif kembali.
 */
class CustomerNotifier
{
    public function __construct(
        private readonly WhatsappNotificationService $whatsapp,
        private readonly SettingService $settings,
    ) {}

    public function invoiceIssued(Invoice $invoice): void
    {
        $customer = $invoice->customer;

        $this->send($customer, WhatsappMessageType::Invoice, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Tagihan internet {$this->company()} periode {$this->date($invoice->period_start)} s.d. {$this->date($invoice->period_end)} sudah terbit.",
            "No. invoice: {$invoice->invoice_number}",
            "Total: {$this->rupiah($invoice->total)}",
            "Jatuh tempo: {$this->date($invoice->due_date)}",
            '',
            "Bayar online (QRIS/VA): {$this->portalUrl($customer)}",
            'Terima kasih.',
        ]));
    }

    public function invoiceReminder(Invoice $invoice): void
    {
        $customer = $invoice->customer;

        $this->send($customer, WhatsappMessageType::Reminder, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Mengingatkan tagihan {$invoice->invoice_number} sebesar {$this->rupiah($invoice->total - $invoice->amount_paid)} jatuh tempo pada {$this->date($invoice->due_date)}.",
            'Mohon lakukan pembayaran sebelum jatuh tempo agar layanan tidak terisolir otomatis.',
            '',
            "Bayar online: {$this->portalUrl($customer)}",
        ]));
    }

    public function paymentReceived(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $customer = $invoice->customer;
        $remaining = max(0, $invoice->total - $invoice->amount_paid);

        $this->send($customer, WhatsappMessageType::Receipt, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Pembayaran {$this->rupiah($payment->amount)} untuk {$invoice->invoice_number} telah kami terima pada {$payment->paid_at->format('d/m/Y H:i')}.",
            $invoice->status === InvoiceStatus::Paid ? 'Status tagihan: LUNAS.' : "Sisa tagihan: {$this->rupiah($remaining)}.",
            "Ref: {$payment->merchant_ref}",
            'Terima kasih.',
        ]));
    }

    public function isolated(Customer $customer): void
    {
        $this->send($customer, WhatsappMessageType::Isolir, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Layanan internet {$this->company()} Anda sementara diisolir karena tagihan telah melewati jatuh tempo.",
            "Lihat tagihan dan bayar online: {$this->portalUrl($customer)}",
            'Layanan aktif kembali otomatis setelah pembayaran diterima.',
        ]));
    }

    public function reopened(Customer $customer): void
    {
        $this->send($customer, WhatsappMessageType::Isolir, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Layanan internet {$this->company()} Anda telah aktif kembali. Bila belum tersambung, silakan matikan lalu nyalakan ulang modem.",
            'Terima kasih.',
        ]));
    }

    public function outage(Customer $customer, Outage $outage): void
    {
        $this->send($customer, WhatsappMessageType::Outage, implode("\n", [
            "Halo {$customer->name},",
            '',
            $outage->message,
            '',
            "— {$this->company()}",
        ]));
    }

    public function outageResolved(Customer $customer, Outage $outage): void
    {
        $this->send($customer, WhatsappMessageType::Outage, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Gangguan jaringan di area Anda ({$outage->title}) telah teratasi. Bila internet belum tersambung, mohon matikan lalu nyalakan ulang modem.",
            'Terima kasih atas kesabarannya.',
            '',
            "— {$this->company()}",
        ]));
    }

    private function send(Customer $customer, WhatsappMessageType $type, string $body): void
    {
        // Pelanggan hasil migrasi bisa belum punya nomor WhatsApp.
        if (blank($customer->whatsapp)) {
            return;
        }

        $this->whatsapp->queue($customer->whatsapp, $type, $body, $customer);
    }

    private function portalUrl(Customer $customer): string
    {
        return route('isolir.show', $customer->customer_code);
    }

    private function company(): string
    {
        return (string) $this->settings->get('company', 'name');
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function date(CarbonInterface $date): string
    {
        return $date->format('d/m/Y');
    }
}
