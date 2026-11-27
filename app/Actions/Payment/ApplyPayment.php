<?php

namespace App\Actions\Payment;

use App\Actions\Vpn\ExtendVpnAccount;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\Network\ReopenCustomerJob;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Network\IsolationService;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Support\Facades\DB;

class ApplyPayment
{
    public function __construct(
        private readonly IsolationService $isolation,
        private readonly CustomerNotifier $notifier,
        private readonly ExtendVpnAccount $extendVpnAccount,
    ) {}

    /**
     * Tandai pembayaran lunas dan bukukan ke invoice-nya.
     * Idempoten: callback gateway yang dikirim ulang tidak menambah amount_paid dua kali.
     * Pelanggan terisolir dibuka otomatis bila tidak ada lagi tagihan lewat jatuh tempo.
     *
     * @return bool true bila pembayaran baru saja diterapkan, false bila sudah pernah.
     */
    public function handle(Payment $payment, ?string $gatewayRef = null): bool
    {
        $applied = DB::transaction(function () use ($payment, $gatewayRef): bool {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Paid) {
                return false;
            }

            $invoice = Invoice::whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $locked->paid_at ?? now(),
                'gateway_ref' => $locked->gateway_ref ?? $gatewayRef,
            ]);

            $amountPaid = $invoice->amount_paid + $locked->amount;
            $isPaid = $amountPaid >= $invoice->total;

            $invoice->update([
                'amount_paid' => $amountPaid,
                'status' => $isPaid ? InvoiceStatus::Paid : InvoiceStatus::Partial,
                'paid_at' => $isPaid ? now() : $invoice->paid_at,
            ]);

            $payment->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if (! $applied) {
            return false;
        }

        $payment->load('invoice.customer', 'invoice.vpnAccount.server');

        if ($payment->method === PaymentMethod::Gateway) {
            activity('Pembayaran')
                ->performedOn($payment->invoice)
                ->withProperties(['invoice' => $payment->invoice->invoice_number, 'amount' => $payment->amount, 'merchant_ref' => $payment->merchant_ref, 'gateway_ref' => $payment->gateway_ref])
                ->log('Pembayaran online Rp '.number_format($payment->amount, 0, ',', '.').' diterima untuk '.$payment->invoice->invoice_number);
        }

        // Tagihan tanpa pelanggan dan tanpa akun VPN (mis. akunnya dihapus sebelum bayar): uang sudah dibukukan,
        // tetapi tidak ada tujuan notifikasi maupun pembukaan layanan.
        if ($payment->invoice->vpn_account_id === null && $payment->invoice->customer === null) {
            return true;
        }

        $this->notifier->paymentReceived($payment);

        if ($payment->invoice->vpn_account_id !== null) {
            if ($payment->invoice->status === InvoiceStatus::Paid) {
                $this->extendVpnAccount->handle($payment->invoice);
            }

            return true;
        }

        $customer = $payment->invoice->customer;

        if ($customer->status === CustomerStatus::Isolated && ! $this->isolation->hasOverdueInvoices($customer)) {
            ReopenCustomerJob::dispatch($customer);
        }

        return true;
    }
}
