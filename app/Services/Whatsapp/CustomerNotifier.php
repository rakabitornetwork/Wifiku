<?php

namespace App\Services\Whatsapp;

use App\Enums\InvoiceStatus;
use App\Enums\WhatsappMessageType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\Payment;
use App\Services\Chat\CustomerTelegramChannel;
use App\Services\Messaging\MessageTemplates;
use App\Services\Settings\SettingService;
use Carbon\CarbonInterface;

/**
 * Pesan otomatis ke pelanggan: invoice, pengingat, bukti bayar, isolir, aktif kembali, janji bayar, dan gangguan.
 * Teks diambil dari MessageTemplates (bawaan atau hasil suntingan Owner); kelas ini mengisi variabelnya dan mengirim
 * ke WhatsApp dan/atau Telegram sesuai pengaturan.
 */
class CustomerNotifier
{
    public function __construct(
        private readonly WhatsappNotificationService $whatsapp,
        private readonly SettingService $settings,
        private readonly VpnNotifier $vpn,
        private readonly CustomerTelegramChannel $telegram,
        private readonly MessageTemplates $templates,
    ) {}

    public function invoiceIssued(Invoice $invoice): void
    {
        if ($invoice->vpn_account_id !== null) {
            $this->vpn->invoiceIssued($invoice);

            return;
        }

        $customer = $invoice->customer;

        $this->send($customer, 'invoice_issued', [
            ...$this->base($customer),
            'periode_mulai' => $this->date($invoice->period_start),
            'periode_selesai' => $this->date($invoice->period_end),
            'no_invoice' => $invoice->invoice_number,
            'total' => $this->rupiah($invoice->total),
            'jatuh_tempo' => $this->date($invoice->due_date),
        ]);
    }

    public function invoiceReminder(Invoice $invoice): void
    {
        if ($invoice->vpn_account_id !== null) {
            $this->vpn->invoiceReminder($invoice);

            return;
        }

        $customer = $invoice->customer;

        $this->send($customer, 'invoice_reminder', [
            ...$this->base($customer),
            'no_invoice' => $invoice->invoice_number,
            'sisa' => $this->rupiah($invoice->total - $invoice->amount_paid),
            'jatuh_tempo' => $this->date($invoice->due_date),
        ]);
    }

    public function invoiceDueToday(Invoice $invoice): void
    {
        $customer = $invoice->customer;

        $this->send($customer, 'invoice_due_today', [
            ...$this->base($customer),
            'no_invoice' => $invoice->invoice_number,
            'sisa' => $this->rupiah(max(0, $invoice->total - $invoice->amount_paid)),
            'jatuh_tempo' => $this->date($invoice->due_date),
        ]);
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

        $this->send($customer, 'payment_received', [
            ...$this->base($customer),
            'jumlah' => $this->rupiah($payment->amount),
            'no_invoice' => $invoice->invoice_number,
            'waktu' => $payment->paid_at->format('d/m/Y H:i'),
            'status_tagihan' => $invoice->status === InvoiceStatus::Paid ? 'Status tagihan: LUNAS.' : "Sisa tagihan: {$this->rupiah($remaining)}.",
            'referensi' => (string) $payment->merchant_ref,
        ]);
    }

    public function isolated(Customer $customer): void
    {
        $this->send($customer, 'isolated', $this->base($customer));
    }

    public function reopened(Customer $customer): void
    {
        $this->send($customer, 'reopened', $this->base($customer));
    }

    public function extensionGranted(Customer $customer, CarbonInterface $until, int $outstanding): void
    {
        $this->send($customer, 'extension_granted', [...$this->base($customer), 'sampai_tanggal' => $this->date($until), 'total' => $this->rupiah($outstanding)]);
    }

    public function extensionEnding(Customer $customer, int $outstanding): void
    {
        $this->send($customer, 'extension_ending', [...$this->base($customer), 'total' => $this->rupiah($outstanding)]);
    }

    public function outage(Customer $customer, Outage $outage): void
    {
        $this->send($customer, 'outage', [...$this->base($customer), 'pesan' => $outage->message]);
    }

    public function outageResolved(Customer $customer, Outage $outage): void
    {
        $this->send($customer, 'outage_resolved', [...$this->base($customer), 'judul' => $outage->title]);
    }

    /**
     * Kode OTP login portal pelanggan; dikirim ke nomor WhatsApp yang sedang masuk (sudah dicocokkan dengan data pelanggan).
     * Teksnya tidak bisa disunting dan tidak ada saklar yang mematikannya.
     */
    public function loginCode(string $phone, string $code, int $ttlMinutes): void
    {
        $this->whatsapp->queue($phone, WhatsappMessageType::AccountSecurity, $this->templates->render('login_code', ['perusahaan' => $this->company(), 'kode' => $code, 'menit' => $ttlMinutes]));
    }

    /**
     * Variabel yang dimiliki setiap pesan ke pelanggan.
     *
     * @return array<string, string>
     */
    private function base(Customer $customer): array
    {
        return [
            'nama' => $customer->name,
            'perusahaan' => $this->company(),
            'kode_pelanggan' => $customer->customer_code,
            'link_bayar' => $this->portalUrl($customer),
            'link_portal' => route('portal.login'),
        ];
    }

    /**
     * Kirim ke WhatsApp dan/atau Telegram (bila pelanggan menautkannya sesuai pengaturan pengiriman).
     * Salinan Telegram diberi ikon sesuai fungsi pesan; WhatsApp tetap teks apa adanya.
     *
     * @param  array<string, scalar|null>  $variables
     */
    private function send(Customer $customer, string $templateKey, array $variables): void
    {
        $template = $this->templates->definition($templateKey);

        // Saklar per jenis pesan (Pengaturan → Notifikasi & Webhook); berlaku untuk WhatsApp dan Telegram sekaligus.
        if ($template['topic'] !== null && $this->settings->get('notify', $template['topic']) === '0') {
            return;
        }

        $body = $this->templates->render($templateKey, $variables);
        $telegramSent = $this->telegram->send($customer, "{$template['icon']} {$body}", $template['type']);

        // Pelanggan hasil migrasi bisa belum punya nomor WhatsApp; bila Telegram diutamakan dan sudah terkirim, WhatsApp dilewati.
        if (blank($customer->whatsapp) || ($telegramSent && $this->telegram->prefersTelegram())) {
            return;
        }

        $this->whatsapp->queue($customer->whatsapp, $template['type'], $body, $customer);
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
