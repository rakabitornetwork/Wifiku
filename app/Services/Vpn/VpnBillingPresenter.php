<?php

namespace App\Services\Vpn;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\PaymentGateway;

/**
 * Bentuk data tagihan VPN untuk halaman pelanggan (halaman bayar per akun dan halaman Tagihan member).
 */
class VpnBillingPresenter
{
    /**
     * @return array{invoice_number: string, account: string|null, period_start: string, period_end: string, due_date: string, total: int, outstanding: int, status: string, is_overdue: bool, checkout_url: string|null, paid_at: string|null, paid_via: string|null}
     */
    public function row(Invoice $invoice): array
    {
        $payments = $invoice->relationLoaded('payments') ? $invoice->payments : collect();
        $pending = $payments->first(fn ($payment): bool => $payment->status === PaymentStatus::Pending && filled($payment->checkout_url) && $payment->expires_at?->isFuture());
        $paid = $payments->first(fn ($payment): bool => $payment->status === PaymentStatus::Paid);

        return [
            'invoice_number' => $invoice->invoice_number,
            'account' => $invoice->vpnAccount?->name,
            'period_start' => $invoice->period_start->toDateString(),
            'period_end' => $invoice->period_end->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'total' => $invoice->total,
            'outstanding' => max(0, $invoice->total - $invoice->amount_paid),
            'status' => $invoice->status->value,
            'is_overdue' => $invoice->due_date->isPast(),
            // Transaksi QRIS/VA yang masih berlaku, agar pelanggan bisa melanjutkan tanpa membuat yang baru.
            'checkout_url' => $pending?->checkout_url,
            'paid_at' => $invoice->paid_at?->toDateString(),
            'paid_via' => $paid === null ? null : ($paid->method === PaymentMethod::Cash ? 'Tunai' : ($paid->channel ?: 'Online')),
        ];
    }

    /**
     * Ketersediaan pembayaran online dari gateway aktif.
     *
     * @return array{available: bool, channels: list<string>}
     */
    public function paymentOptions(): array
    {
        $gateway = PaymentGateway::where('is_active', true)->orderBy('sort_order')->first();

        return [
            'available' => $gateway !== null && ! empty($gateway->enabled_channels),
            'channels' => $gateway?->enabled_channels ?? [],
        ];
    }
}
