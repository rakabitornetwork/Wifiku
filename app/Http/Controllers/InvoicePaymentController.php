<?php

namespace App\Http\Controllers;

use App\Actions\Payment\RecordCashPayment;
use App\Models\Invoice;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoicePaymentController extends Controller
{
    /**
     * Pembayaran tunai di kasir.
     */
    public function store(Request $request, Invoice $invoice, RecordCashPayment $recordCashPayment): RedirectResponse
    {
        $validated = $request->validate(['amount' => ['required', 'integer', 'min:1']]);

        $payment = $recordCashPayment->handle($invoice, (int) $validated['amount'], $request->user());

        Toast::success("Pembayaran tunai {$payment->merchant_ref} dicatat.");

        return back();
    }
}
