<?php

namespace App\Services\Whatsapp;

use App\Enums\VpnAccountStatus;
use App\Enums\WhatsappMessageType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\VpnAccount;
use App\Services\Settings\SettingService;
use Carbon\CarbonInterface;

/**
 * Template pesan WhatsApp untuk pembeli layanan VPN Tunnel: tagihan, pengingat, bukti bayar, dan status akun.
 */
class VpnNotifier
{
    public function __construct(
        private readonly WhatsappNotificationService $whatsapp,
        private readonly SettingService $settings,
    ) {}

    public function invoiceIssued(Invoice $invoice): void
    {
        $account = $invoice->vpnAccount;

        $this->send($account, WhatsappMessageType::Invoice, implode("\n", [
            "Halo {$account->name},",
            '',
            ($account->status === VpnAccountStatus::Pending ? 'Tagihan aktivasi' : 'Tagihan perpanjangan')." VPN Tunnel {$this->company()} (akun {$account->username}) periode {$this->date($invoice->period_start)} s.d. {$this->date($invoice->period_end)} sudah terbit.",
            "No. invoice: {$invoice->invoice_number}",
            "Total: {$this->rupiah($invoice->total)}",
            "Jatuh tempo: {$this->date($invoice->due_date)}",
            '',
            "Bayar online (QRIS/VA): {$this->portalUrl($account)}",
            'Terima kasih.',
        ]));
    }

    public function invoiceReminder(Invoice $invoice): void
    {
        $account = $invoice->vpnAccount;

        $this->send($account, WhatsappMessageType::Reminder, implode("\n", [
            "Halo {$account->name},",
            '',
            "Mengingatkan tagihan {$invoice->invoice_number} sebesar {$this->rupiah($invoice->total - $invoice->amount_paid)} untuk VPN Tunnel akun {$account->username} jatuh tempo pada {$this->date($invoice->due_date)}.",
            'Bila tidak dibayar, tunnel VPN akan terputus otomatis setelah masa berlaku habis.',
            '',
            "Bayar online: {$this->portalUrl($account)}",
        ]));
    }

    public function paymentReceived(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $account = $invoice->vpnAccount;
        $remaining = max(0, $invoice->total - $invoice->amount_paid);

        $this->send($account, WhatsappMessageType::Receipt, implode("\n", [
            "Halo {$account->name},",
            '',
            "Pembayaran {$this->rupiah($payment->amount)} untuk {$invoice->invoice_number} telah kami terima pada {$payment->paid_at->format('d/m/Y H:i')}.",
            $remaining === 0 ? 'Status tagihan: LUNAS.' : "Sisa tagihan: {$this->rupiah($remaining)}.",
            "Ref: {$payment->merchant_ref}",
            'Terima kasih.',
        ]));
    }

    public function extended(VpnAccount $account, bool $reopened, bool $firstActivation = false): void
    {
        $this->send($account, WhatsappMessageType::Receipt, implode("\n", [
            "Halo {$account->name},",
            '',
            $firstActivation
                ? "Pembayaran diterima. VPN Tunnel akun {$account->username} sudah aktif dan berlaku sampai {$this->date($account->expires_at)}. Buka portal untuk melihat script RouterOS: {$this->memberUrl()}"
                : ($reopened
                ? "VPN Tunnel akun {$account->username} aktif kembali dan berlaku sampai {$this->date($account->expires_at)}. Tunnel akan tersambung otomatis."
                : "VPN Tunnel akun {$account->username} diperpanjang sampai {$this->date($account->expires_at)}."),
            'Terima kasih.',
        ]));
    }

    public function suspended(VpnAccount $account): void
    {
        $this->send($account, WhatsappMessageType::Isolir, implode("\n", [
            "Halo {$account->name},",
            '',
            "Masa berlaku VPN Tunnel akun {$account->username} telah habis sehingga tunnel diputus.",
            "Perpanjang dan aktifkan kembali otomatis lewat: {$this->portalUrl($account)}",
        ]));
    }

    private function send(VpnAccount $account, WhatsappMessageType $type, string $body): void
    {
        if (blank($account->whatsapp)) {
            return;
        }

        $this->whatsapp->queue($account->whatsapp, $type, $body);
    }

    private function memberUrl(): string
    {
        return route('vpn-member.dashboard');
    }

    private function portalUrl(VpnAccount $account): string
    {
        return route('vpn-portal.show', $account->portal_token);
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
