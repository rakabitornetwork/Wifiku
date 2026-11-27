<?php

namespace App\Services\Whatsapp;

use App\Enums\InvoiceStatus;
use App\Enums\WhatsappMessageType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\Payment;
use App\Services\Billing\ProrataCalculator;
use App\Services\Chat\CustomerTelegramChannel;
use App\Services\Messaging\MessageTemplates;
use App\Services\Settings\SettingService;
use Carbon\CarbonImmutable;
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
        private readonly ProrataCalculator $prorata,
    ) {}

    public function welcome(Customer $customer): void
    {
        $this->send($customer, 'welcome', [...$this->base($customer), 'info_tagihan' => $this->billingPreview($customer)]);
    }

    /**
     * Ringkasan singkat tagihan pertama (prorata bila mulai di tengah siklus) dan tagihan sesudahnya, dihitung dari data pelanggan.
     * Invoice pertama dibuat penjadwal pukul 08.00, jadi terbit hari ini bila pelanggan didaftarkan sebelum jam itu.
     */
    private function billingPreview(Customer $customer): string
    {
        $package = $customer->package;

        if ($package === null || $customer->installed_at === null) {
            return '';
        }

        $price = $package->price;
        $today = CarbonImmutable::today();
        $day = CarbonImmutable::parse($customer->installed_at)->startOfDay()->max($today);
        [$cycleStart, $cycleEnd] = $this->prorata->cycleContaining($customer->billing_day, $day);
        $installed = CarbonImmutable::parse($customer->installed_at)->startOfDay();
        $issue = $day->equalTo($today) && now()->hour >= 8 ? $day->addDay() : $day;
        $due = $issue->addDays($customer->due_days);

        $first = $installed->greaterThan($cycleStart)
            ? $this->prorata->calculate($price, $installed, $cycleStart, $cycleEnd)
            : null;

        $firstLine = $first
            ? "• Tagihan pertama (prorata {$first['days']}/{$first['period_days']} hari): {$this->rupiah($first['amount'])}, terbit {$this->date($issue)}, jatuh tempo {$this->date($due)}."
            : "• Tagihan pertama: {$this->rupiah($price)} (1 bulan penuh), terbit {$this->date($issue)}, jatuh tempo {$this->date($due)}.";

        return implode("\n", [
            'Info tagihan:',
            $firstLine,
            "• Selanjutnya {$this->rupiah($price)}/bulan, terbit tiap tanggal {$customer->billing_day}, jatuh tempo {$customer->due_days} hari setelahnya.",
        ]);
    }

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
            'rincian' => $this->invoiceBreakdown($invoice, $customer),
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

    /**
     * Rincian tagihan: perhitungan prorata untuk tagihan pertama, tunggakan sebelumnya, dan kapan tagihan berikutnya terbit.
     */
    private function invoiceBreakdown(Invoice $invoice, Customer $customer): string
    {
        $package = $customer->package;
        $monthly = $package?->price ?? $invoice->total;
        $lines = ['Rincian tagihan:'];

        if ($package !== null) {
            $lines[] = "• Paket: {$package->name} ({$this->rupiah($monthly)}/bulan)";
        }

        if ($invoice->is_prorated && $invoice->prorata_days && $invoice->period_days) {
            $lines[] = "• Layanan aktif sejak {$this->date($invoice->period_start)}, jadi ditagih {$invoice->prorata_days} dari {$invoice->period_days} hari (prorata bulan pertama).";
            $lines[] = "• Perhitungan: {$this->rupiah($monthly)} × {$invoice->prorata_days}/{$invoice->period_days} = {$this->rupiah($invoice->total)}";
        } else {
            $lines[] = "• Periode: {$this->date($invoice->period_start)} s.d. {$this->date($invoice->period_end)}";
            $lines[] = "• Biaya bulan ini: {$this->rupiah($invoice->total)}";
        }

        $arrears = Invoice::query()
            ->where('customer_id', $invoice->customer_id)
            ->whereKeyNot($invoice->id)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->get(['total', 'amount_paid']);

        if ($arrears->isNotEmpty()) {
            $lines[] = "• Tunggakan sebelumnya: {$this->rupiah((int) $arrears->sum(fn (Invoice $item): int => $item->total - $item->amount_paid))} ({$arrears->count()} tagihan)";
        }

        $next = CarbonImmutable::parse($invoice->period_end)->addDay();
        $nextAmount = $invoice->is_prorated ? ' Mulai tagihan berikutnya, biaya penuh '.$this->rupiah($monthly).' per bulan.' : '';
        $lines[] = "• Tagihan berikutnya terbit {$this->date($next)}, jatuh tempo {$customer->due_days} hari setelahnya.{$nextAmount}";

        return implode("\n", $lines);
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
