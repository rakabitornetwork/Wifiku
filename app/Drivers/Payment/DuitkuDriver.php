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
use Illuminate\Support\Str;

/**
 * Duitku API v2 (inquiry).
 *
 * Signature transaksi: MD5(merchantCode . merchantOrderId . paymentAmount . apiKey).
 * Signature callback: MD5(merchantCode . amount . merchantOrderId . apiKey), dikirim sebagai form POST.
 */
class DuitkuDriver extends AbstractPaymentDriver
{
    public static function requiredCredentials(): array
    {
        return ['merchant_code', 'api_key'];
    }

    protected function baseUrl(): string
    {
        return $this->gateway->is_sandbox
            ? 'https://sandbox.duitku.com/webapi/api/merchant'
            : 'https://passport.duitku.com/webapi/api/merchant';
    }

    public function createTransaction(TransactionRequest $request): TransactionResult
    {
        $merchantCode = $this->credential('merchant_code');

        $response = $this->send(
            fn () => $this->http()->post('/v2/inquiry', [
                'merchantCode' => $merchantCode,
                'paymentAmount' => $request->amount,
                'paymentMethod' => $request->channel,
                'merchantOrderId' => $request->merchantRef,
                'productDetails' => $request->description,
                'email' => $request->customerEmail,
                'phoneNumber' => $request->customerPhone,
                'customerVaName' => Str::limit($request->customerName, 20, ''),
                'callbackUrl' => $request->callbackUrl,
                'returnUrl' => $request->returnUrl,
                'expiryPeriod' => $request->expiresInMinutes,
                'signature' => md5($merchantCode.$request->merchantRef.$request->amount.$this->credential('api_key')),
            ]),
            fn (Response $response) => $response->json('Message') ?? $response->json('statusMessage') ?? $response->body(),
        );

        if ($response->json('statusCode') !== '00') {
            throw new PaymentGatewayException('Duitku menolak transaksi: '.($response->json('statusMessage') ?? 'tanpa pesan'));
        }

        return new TransactionResult(
            gatewayRef: $response->json('reference'),
            checkoutUrl: $response->json('paymentUrl'),
            qrString: $response->json('qrString') ?: null,
            vaNumber: $response->json('vaNumber') ?: null,
            expiresAt: CarbonImmutable::now()->addMinutes($request->expiresInMinutes),
            raw: $response->json(),
        );
    }

    public function verifyCallback(Request $request): bool
    {
        $merchantCode = (string) $request->input('merchantCode');
        $signature = $request->input('signature');

        if (! is_string($signature) || $signature === '' || ! hash_equals($this->credential('merchant_code'), $merchantCode)) {
            return false;
        }

        $expected = md5($merchantCode.$request->input('amount').$request->input('merchantOrderId').$this->credential('api_key'));

        return hash_equals($expected, $signature);
    }

    public function parseCallback(Request $request): CallbackResult
    {
        return new CallbackResult(
            merchantRef: (string) $request->input('merchantOrderId'),
            status: match ((string) $request->input('resultCode')) {
                '00' => PaymentStatus::Paid,
                '01' => PaymentStatus::Failed,
                default => PaymentStatus::Pending,
            },
            amount: (int) round((float) $request->input('amount')),
            gatewayRef: $request->input('reference'),
            raw: $request->all(),
        );
    }

    public function availableChannels(): array
    {
        $merchantCode = $this->credential('merchant_code');
        $amount = 10000;
        $datetime = now()->format('Y-m-d H:i:s');

        $response = $this->send(fn () => $this->http()->post('/paymentmethod/getpaymentmethod', [
            'merchantcode' => $merchantCode,
            'amount' => $amount,
            'datetime' => $datetime,
            'signature' => hash('sha256', $merchantCode.$amount.$datetime.$this->credential('api_key')),
        ]));

        return collect($response->json('paymentFee', []))
            ->map(fn (array $channel): array => ['code' => (string) $channel['paymentMethod'], 'name' => (string) $channel['paymentName']])
            ->values()
            ->all();
    }
}
