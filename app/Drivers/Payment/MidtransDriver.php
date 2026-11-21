<?php

namespace App\Drivers\Payment;

use App\Drivers\Payment\Data\CallbackResult;
use App\Drivers\Payment\Data\TransactionRequest;
use App\Drivers\Payment\Data\TransactionResult;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Midtrans Snap (halaman checkout) + HTTP Notification.
 *
 * Signature notifikasi: SHA512(order_id . status_code . gross_amount . server_key).
 * URL notifikasi per transaksi dikirim lewat header X-Override-Notification.
 */
class MidtransDriver extends AbstractPaymentDriver
{
    /**
     * Kode channel Snap (enabled_payments) yang umum dipakai ISP.
     *
     * @var array<string, string>
     */
    private const CHANNELS = [
        'other_qris' => 'QRIS',
        'gopay' => 'GoPay',
        'shopeepay' => 'ShopeePay',
        'bca_va' => 'BCA Virtual Account',
        'bni_va' => 'BNI Virtual Account',
        'bri_va' => 'BRI Virtual Account',
        'permata_va' => 'Permata Virtual Account',
        'cimb_va' => 'CIMB Niaga Virtual Account',
        'echannel' => 'Mandiri Bill Payment',
        'indomaret' => 'Indomaret',
        'alfamart' => 'Alfamart',
    ];

    public static function requiredCredentials(): array
    {
        return ['server_key', 'client_key'];
    }

    protected function baseUrl(): string
    {
        return $this->gateway->is_sandbox
            ? 'https://api.sandbox.midtrans.com/v2'
            : 'https://api.midtrans.com/v2';
    }

    private function snapUrl(): string
    {
        return $this->gateway->is_sandbox
            ? 'https://app.sandbox.midtrans.com/snap/v1'
            : 'https://app.midtrans.com/snap/v1';
    }

    public function createTransaction(TransactionRequest $request): TransactionResult
    {
        $response = $this->send(
            fn () => $this->http($this->snapUrl())
                ->withBasicAuth($this->credential('server_key'), '')
                ->withHeaders(['X-Override-Notification' => $request->callbackUrl])
                ->post('/transactions', [
                    'transaction_details' => ['order_id' => $request->merchantRef, 'gross_amount' => $request->amount],
                    'item_details' => [[
                        'id' => Str::limit($request->merchantRef, 50, ''),
                        'price' => $request->amount,
                        'quantity' => 1,
                        'name' => Str::limit($request->description, 50, ''),
                    ]],
                    'customer_details' => [
                        'first_name' => $request->customerName,
                        'phone' => $request->customerPhone,
                        'email' => $request->customerEmail,
                    ],
                    'enabled_payments' => [$request->channel],
                    'expiry' => ['unit' => 'minute', 'duration' => $request->expiresInMinutes],
                    'callbacks' => ['finish' => $request->returnUrl],
                ]),
            fn (Response $response) => implode('; ', (array) $response->json('error_messages', [])),
        );

        return new TransactionResult(
            // ID transaksi Midtrans baru diketahui dari notifikasi; order_id = merchant_ref sudah cukup sebagai kunci.
            gatewayRef: null,
            checkoutUrl: $response->json('redirect_url'),
            expiresAt: CarbonImmutable::now()->addMinutes($request->expiresInMinutes),
            raw: $response->json(),
        );
    }

    public function verifyCallback(Request $request): bool
    {
        $signature = $request->input('signature_key');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash('sha512', $request->input('order_id').$request->input('status_code').$request->input('gross_amount').$this->credential('server_key'));

        return hash_equals($expected, $signature);
    }

    public function parseCallback(Request $request): CallbackResult
    {
        $fraud = $request->input('fraud_status');

        return new CallbackResult(
            merchantRef: (string) $request->input('order_id'),
            status: match ((string) $request->input('transaction_status')) {
                'settlement' => PaymentStatus::Paid,
                'capture' => $fraud === null || $fraud === 'accept' ? PaymentStatus::Paid : PaymentStatus::Pending,
                'deny', 'cancel', 'failure', 'refund', 'partial_refund' => PaymentStatus::Failed,
                'expire' => PaymentStatus::Expired,
                default => PaymentStatus::Pending,
            },
            amount: (int) round((float) $request->input('gross_amount')),
            gatewayRef: $request->input('transaction_id'),
            raw: $request->all(),
        );
    }

    public function availableChannels(): array
    {
        return collect(self::CHANNELS)->map(fn (string $name, string $code): array => ['code' => $code, 'name' => $name])->values()->all();
    }
}
