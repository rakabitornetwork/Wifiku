<?php

namespace App\Actions\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RecordCashPayment
{
    public function __construct(private readonly ApplyPayment $applyPayment) {}

    /**
     * Catat pembayaran tunai di kasir; nominal tidak boleh melebihi sisa tagihan.
     */
    public function handle(Invoice $invoice, int $amount, User $cashier): Payment
    {
        $outstanding = $invoice->total - $invoice->amount_paid;

        if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true) || $outstanding <= 0) {
            throw ValidationException::withMessages(['amount' => 'Invoice ini sudah lunas atau dibatalkan.']);
        }

        if ($amount > $outstanding) {
            throw ValidationException::withMessages(['amount' => 'Nominal melebihi sisa tagihan ('.number_format($outstanding, 0, ',', '.').').']);
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'method' => PaymentMethod::Cash,
            'merchant_ref' => Payment::newReference('CASH'),
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'received_by' => $cashier->id,
        ]);

        $this->applyPayment->handle($payment);

        activity('Pembayaran')
            ->performedOn($invoice)
            ->causedBy($cashier)
            ->withProperties(['invoice' => $invoice->invoice_number, 'amount' => $amount, 'merchant_ref' => $payment->merchant_ref])
            ->log('Pembayaran tunai Rp '.number_format($amount, 0, ',', '.').' dicatat untuk '.$invoice->invoice_number);

        return $payment->refresh();
    }
}
