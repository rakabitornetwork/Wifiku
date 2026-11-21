<?php

namespace App\Http\Controllers\Webhooks;

use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Drivers\Payment\PaymentGatewayManager;
use App\Enums\PaymentGatewayDriver;
use App\Http\Controllers\Controller;
use App\Jobs\Payment\ProcessPaymentCallbackJob;
use App\Models\PaymentCallback;
use App\Models\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    /**
     * Terima callback Duitku/Tripay/Midtrans: catat, verifikasi signature, lalu proses di Queue.
     * Callback tanpa signature valid dicatat untuk audit tetapi tidak pernah mengubah data pembayaran.
     */
    public function __invoke(Request $request, string $driver, PaymentGatewayManager $gateways): JsonResponse
    {
        $driver = PaymentGatewayDriver::tryFrom($driver) ?? abort(404);
        $gateway = PaymentGateway::where('driver', $driver)->firstOrFail();
        $implementation = $gateways->driver($gateway);

        try {
            $valid = $implementation->verifyCallback($request);
        } catch (PaymentGatewayException) {
            // Kredensial belum diatur: signature tidak dapat diverifikasi.
            $valid = false;
        }

        $result = $valid ? $implementation->parseCallback($request) : null;

        $callback = PaymentCallback::create([
            'driver' => $driver,
            'merchant_ref' => $result?->merchantRef ?: $request->input('merchant_ref', $request->input('merchantOrderId', $request->input('order_id'))),
            'signature_valid' => $valid,
            'payload' => $request->all() ?: ['raw' => $request->getContent()],
            'ip_address' => $request->ip(),
            'error' => $valid ? null : 'Signature tidak valid.',
        ]);

        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        ProcessPaymentCallbackJob::dispatch($callback, $result->merchantRef, $result->status, $result->amount, $result->gatewayRef);

        return response()->json(['success' => true]);
    }
}
