<?php

namespace App\Services\Whatsapp;

use App\Enums\InvoiceStatus;
use App\Enums\WhatsappMessageType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\Payment;
use App\Services\Chat\CustomerTelegramChannel;
use App\Services\Settings\SettingService;
use Carbon\CarbonInterface;

/**
 * Template pesan WhatsApp untuk pelanggan: invoice, pengingat H-3, bukti bayar, isolir, dan aktif kembali.
 */
class CustomerNotifier
{
    /**
     * Ikon bawaan salinan Telegram menurut jenis pesan.
     *
     * @var array<string, string>
     */
    private const ICONS = ['invoice' => '🧾', 'reminder' => '⏰', 'receipt' => '💳', 'outage' => '⚠️', 'isolir' => '🔴', 'test' => '🧪'];

    public function __construct(
        private readonly WhatsappNotificationService $whatsapp,
        private readonly SettingService $settings,
        private readonly VpnNotifier $vpn,
        private readonly CustomerTelegramChannel $telegram,
    ) {}

    public function invoiceIssued(Invoice $invoice): void
    {
        if ($invoice->vpn_account_id !== null) {
            $this->vpn->invoiceIssued($invoice);

            return;
        }

        $customer = $invoice->customer;

        $this->send($customer, WhatsappMessageType::Invoice, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Tagihan internet {$this->company()} periode {$this->date($invoice->period_start)} s.d. {$this->date($invoice->period_end)} sudah terbit.",
            "No. invoice: {$invoice->invoice_number}",
            "Total: {$this->rupiah($invoice->total)}",
            "Jatuh tempo: {$this->date($invoice->due_date)}",
            "Kode pelanggan: {$customer->customer_code}",
            '',
            "Bayar online (QRIS/VA): {$this->portalUrl($customer)}",
            'Cek status ONU & ganti WiFi: '.route('portal.login').' (masuk dengan nomor WhatsApp ini)',
            'Terima kasih.',
        ]));
    }

    public function invoiceReminder(Invoice $invoice): void
    {
        if ($invoice->vpn_account_id !== null) {
            $this->vpn->invoiceReminder($invoice);

            return;
        }

        $customer = $invoice->customer;

        $this->send($customer, WhatsappMessageType::Reminder, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Mengingatkan tagihan {$invoice->invoice_number} sebesar {$this->rupiah($invoice->total - $invoice->amount_paid)} jatuh tempo pada {$this->date($invoice->due_date)}.",
            'Mohon lakukan pembayaran sebelum jatuh tempo agar layanan tidak terisolir otomatis.',
            "Kode pelanggan: {$customer->customer_code}",
            '',
            "Bayar online: {$this->portalUrl($customer)}",
        ]));
    }

    public function paymentReceived(Payment $payment): void
    {
        $invoice = $payment->invoice;

        if ($invoice->vpn_account_id !== null) {
            $this->vpn->paymentReceived($payment);

            return;
        }

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
        ]), '🟢');
    }

    public function extensionGranted(Customer $customer, CarbonInterface $until, int $outstanding): void
    {
        $this->send($customer, WhatsappMessageType::Reminder, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Permintaan perpanjangan pembayaran Anda disetujui. Layanan internet {$this->company()} tetap aktif sampai {$this->date($until)}.",
            "Total tagihan yang harus dilunasi: {$this->rupiah($outstanding)}.",
            "Lihat tagihan dan bayar online: {$this->portalUrl($customer)}",
            'Mohon lunasi sebelum tanggal tersebut agar layanan tidak terisolir.',
        ]), '🤝');
    }

    public function extensionEnding(Customer $customer, int $outstanding): void
    {
        $this->send($customer, WhatsappMessageType::Reminder, implode("\n", [
            "Halo {$customer->name},",
            '',
            "Masa perpanjangan pembayaran Anda berakhir besok. Tagihan yang belum lunas: {$this->rupiah($outstanding)}.",
            "Bayar online: {$this->portalUrl($customer)}",
            'Bila belum dibayar, layanan akan diisolir otomatis.',
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
        ]), '✅');
    }

    /**
     * Kode OTP login portal pelanggan; dikirim ke nomor WhatsApp yang sedang masuk (sudah dicocokkan dengan data pelanggan).
     */
    public function loginCode(string $phone, string $code, int $ttlMinutes): void
    {
        $this->whatsapp->queue($phone, WhatsappMessageType::AccountSecurity, implode("\n", [
            'Halo,',
            '',
            "Kode masuk portal pelanggan {$this->company()}: *{$code}*",
            "Berlaku {$ttlMinutes} menit. Jangan bagikan kode ini kepada siapa pun, termasuk petugas.",
            '',
            'Abaikan pesan ini bila Anda tidak memintanya.',
        ]));
    }

    /**
     * Kirim ke WhatsApp dan/atau Telegram (bila pelanggan menautkannya sesuai pengaturan pengiriman).
     * Salinan Telegram diberi ikon sesuai fungsi pesan; WhatsApp tetap teks apa adanya.
     */
    private function send(Customer $customer, WhatsappMessageType $type, string $body, ?string $icon = null): void
    {
        $telegramSent = $this->telegram->send($customer, ($icon ?? self::ICONS[$type->value] ?? '🔔').' '.$body);

        // Pelanggan hasil migrasi bisa belum punya nomor WhatsApp; bila Telegram diutamakan dan sudah terkirim, WhatsApp dilewati.
        if (blank($customer->whatsapp) || ($telegramSent && $this->telegram->prefersTelegram())) {
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
