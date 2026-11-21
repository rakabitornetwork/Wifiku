<?php

namespace App\Drivers\Payment;

use App\Drivers\Payment\Data\CallbackResult;
use App\Drivers\Payment\Data\TransactionRequest;
use App\Drivers\Payment\Data\TransactionResult;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * Tripay closed payment (QRIS, Virtual Account, dll).
 *
 * Signature transaksi: HMAC-SHA256(merchant_code . merchant_ref . amount, private_key).
 * Signature callback: HMAC-SHA256(raw JSON body, private_key) pada header X-Callback-Signature.
 */
class TripayDriver extends AbstractPaymentDriver
{
    public static function requiredCredentials(): array
    {
        return ['merchant_code', 'api_key', 'private_key'];
    }

    protected function baseUrl(): string
    {
        return $this->gateway->is_sandbox
            ? 'https://tripay.co.id/api-sandbox'
            : 'https://tripay.co.id/api';
    }

    public function createTransaction(TransactionRequest $request): TransactionResult
    {
        $merchantCode = $this->credential('merchant_code');

        $response = $this->send(
            fn () => $this->http()->withToken($this->credential('api_key'))->post('/transaction/create', [
                'method' => $request->channel,
                'merchant_ref' => $request->merchantRef,
                'amount' => $request->amount,
                'customer_name' => $request->customerName,
                'customer_email' => $request->customerEmail,
                'customer_phone' => $request->customerPhone,
                'order_items' => [[
                    'sku' => $request->merchantRef,
                    'name' => $request->description,
                    'price' => $request->amount,
                    'quantity' => 1,
                ]],
                'callback_url' => $request->callbackUrl,
                'return_url' => $request->returnUrl,
                'expired_time' => now()->addMinutes($request->expiresInMinutes)->timestamp,
                'signature' => hash_hmac('sha256', $merchantCode.$request->merchantRef.$request->amount, $this->credential('private_key')),
            ]),
            fn (Response $response) => $response->json('message'),
        );

        if ($response->json('success') !== true) {
            throw new PaymentGatewayException('Tripay menolak transaksi: '.($response->json('message') ?? 'tanpa pesan'));
        }

        $data = $response->json('data');

        return new TransactionResult(
            gatewayRef: $data['reference'] ?? null,
            checkoutUrl: $data['checkout_url'] ?? null,
            qrString: $data['qr_string'] ?? null,
            vaNumber: $data['pay_code'] ?? null,
            fee: (int) ($data['total_fee'] ?? 0),
            expiresAt: isset($data['expired_time']) ? CarbonImmutable::createFromTimestamp($data['expired_time']) : null,
            raw: $data,
        );
    }

    public function verifyCallback(Request $request): bool
    {
        $signature = $request->header('X-Callback-Signature');

        if (! is_string($signature) || $signature === '' || $request->header('X-Callback-Event', 'payment_status') !== 'payment_status') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $this->credential('private_key')), $signature);
    }

    public function parseCallback(Request $request): CallbackResult
    {
        $payload = json_decode($request->getContent(), true) ?: [];

        return new CallbackResult(
            merchantRef: (string) ($payload['merchant_ref'] ?? ''),
            status: match (strtoupper((string) ($payload['status'] ?? ''))) {
                'PAID' => PaymentStatus::Paid,
                'EXPIRED' => PaymentStatus::Expired,
                'FAILED', 'REFUND' => PaymentStatus::Failed,
                default => PaymentStatus::Pending,
            },
            // total_amount sudah termasuk biaya yang dibebankan ke pelanggan; nominal tagihan = total - fee_customer.
            amount: (int) ($payload['total_amount'] ?? 0) - (int) ($payload['fee_customer'] ?? 0),
            gatewayRef: $payload['reference'] ?? null,
            raw: $payload,
        );
    }

    public function availableChannels(): array
    {
        $response = $this->send(
            fn () => $this->http()->withToken($this->credential('api_key'))->get('/merchant/payment-channel'),
            fn (Response $response) => $response->json('message'),
        );

        return collect($response->json('data', []))
            ->filter(fn (array $channel): bool => (bool) ($channel['active'] ?? true))
            ->map(fn (array $channel): array => ['code' => (string) $channel['code'], 'name' => (string) $channel['name']])
            ->values()
            ->all();
    }
}
