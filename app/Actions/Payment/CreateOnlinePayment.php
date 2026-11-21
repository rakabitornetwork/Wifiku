<?php

namespace App\Actions\Payment;

use App\Drivers\Payment\Data\TransactionRequest;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Drivers\Payment\PaymentGatewayManager;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Settings\SettingService;
use Illuminate\Validation\ValidationException;

class CreateOnlinePayment
{
    private const EXPIRES_IN_MINUTES = 1440;

    public function __construct(private readonly PaymentGatewayManager $gateways, private readonly SettingService $settings) {}

    /**
     * Buat transaksi QRIS/VA untuk sisa tagihan invoice di gateway aktif.
     * Transaksi pending yang masih berlaku untuk channel dan nominal yang sama dipakai ulang
     * agar pelanggan yang menekan "Bayar" berkali-kali tidak membuat banyak tagihan di gateway.
     */
    public function handle(Invoice $invoice, string $channel): Payment
    {
        $outstanding = $invoice->total - $invoice->amount_paid;

        if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true) || $outstanding <= 0) {
            throw ValidationException::withMessages(['invoice_number' => 'Invoice ini sudah lunas.']);
        }

        $gateway = $this->gateways->activeGateway();

        if (! in_array($channel, $gateway->enabled_channels ?? [], true)) {
            throw ValidationException::withMessages(['channel' => 'Metode pembayaran tidak tersedia.']);
        }

        $reusable = $invoice->payments()
            ->where('payment_gateway_id', $gateway->id)
            ->where('channel', $channel)
            ->where('status', PaymentStatus::Pending)
            ->where('amount', $outstanding)
            ->whereNotNull('checkout_url')
            ->where('expires_at', '>', now()->addMinutes(10))
            ->latest('id')
            ->first();

        if ($reusable !== null) {
            return $reusable;
        }

        $invoice->loadMissing('customer');
        $customer = $invoice->customer;

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'method' => PaymentMethod::Gateway,
            'channel' => $channel,
            'merchant_ref' => Payment::newReference('INV'.$invoice->id),
            'amount' => $outstanding,
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $result = $this->gateways->driver($gateway)->createTransaction(new TransactionRequest(
                merchantRef: $payment->merchant_ref,
                amount: $outstanding,
                channel: $channel,
                customerName: $customer->name,
                // Gateway (mis. Tripay) mewajibkan nomor telepon; pelanggan tanpa WhatsApp memakai nomor CS perusahaan.
                customerPhone: $customer->whatsapp ?: preg_replace('/\D+/', '', (string) $this->settings->get('company', 'cs_whatsapp')),
                description: "Tagihan internet {$invoice->invoice_number}",
                callbackUrl: route('webhooks.payment', $gateway->driver->value),
                returnUrl: route('isolir.show', $customer->customer_code),
                customerEmail: strtolower($customer->customer_code).'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'pelanggan.local'),
                expiresInMinutes: self::EXPIRES_IN_MINUTES,
            ));
        } catch (PaymentGatewayException $exception) {
            $payment->update(['status' => PaymentStatus::Failed]);

            throw $exception;
        }

        $payment->update([
            'gateway_ref' => $result->gatewayRef,
            'checkout_url' => $result->checkoutUrl,
            'qr_string' => $result->qrString,
            'va_number' => $result->vaNumber,
            'fee' => $result->fee,
            'expires_at' => $result->expiresAt,
        ]);

        return $payment;
    }
}
