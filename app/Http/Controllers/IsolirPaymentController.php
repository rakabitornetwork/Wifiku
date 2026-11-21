<?php

namespace App\Http\Controllers;

use App\Actions\Payment\CreateOnlinePayment;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Models\Customer;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class IsolirPaymentController extends Controller
{
    /**
     * Buat transaksi QRIS/VA untuk satu invoice pelanggan lalu arahkan ke halaman checkout gateway.
     */
    public function __invoke(Request $request, Customer $customer, CreateOnlinePayment $createOnlinePayment): Response|RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
            'channel' => ['required', 'string', 'max:50'],
        ]);

        $invoice = $customer->invoices()->where('invoice_number', $validated['invoice_number'])->firstOrFail();

        try {
            $payment = $createOnlinePayment->handle($invoice, $validated['channel']);
        } catch (PaymentGatewayException $exception) {
            Log::warning('Gagal membuat transaksi pembayaran online.', ['invoice_id' => $invoice->id, 'error' => $exception->getMessage()]);
            Toast::error('Pembayaran online sedang tidak dapat diproses. Silakan coba lagi atau hubungi CS.');

            return back();
        }

        if (blank($payment->checkout_url)) {
            Toast::error('Gateway tidak mengembalikan halaman pembayaran. Silakan hubungi CS.');

            return back();
        }

        return Inertia::location($payment->checkout_url);
    }
}
