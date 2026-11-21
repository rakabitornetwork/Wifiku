<?php

namespace Tests\Feature\Drivers\Payment;

use App\Drivers\Payment\Data\TransactionRequest;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Drivers\Payment\TripayDriver;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentStatus;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TripayDriverTest extends TestCase
{
    use RefreshDatabase;

    private function driver(bool $sandbox = true): TripayDriver
    {
        return new TripayDriver(PaymentGateway::factory()->create([
            'driver' => PaymentGatewayDriver::Tripay,
            'is_sandbox' => $sandbox,
            'credentials' => ['merchant_code' => 'T0001', 'api_key' => 'api-key', 'private_key' => 'private-key'],
        ]));
    }

    private function request(): TransactionRequest
    {
        return new TransactionRequest(
            merchantRef: 'INV1-REF', amount: 200000, channel: 'QRIS', customerName: 'Budi', customerPhone: '6281234567890',
            description: 'Tagihan internet INV/2611/00001', callbackUrl: 'https://isp.test/webhooks/payment/tripay',
            returnUrl: 'https://isp.test/isolir/TT-2611-0001', customerEmail: 'tt-2611-0001@isp.test',
        );
    }

    private function callbackRequest(array $payload, ?string $signature = null, string $event = 'payment_status'): Request
    {
        $body = json_encode($payload);
        $request = Request::create('/webhooks/payment/tripay', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        $request->headers->set('X-Callback-Signature', $signature ?? hash_hmac('sha256', $body, 'private-key'));
        $request->headers->set('X-Callback-Event', $event);

        return $request;
    }

    public function test_creates_signed_closed_payment_transaction(): void
    {
        Http::fake(['tripay.co.id/api-sandbox/transaction/create' => Http::response([
            'success' => true,
            'data' => ['reference' => 'T0001-REF', 'checkout_url' => 'https://tripay.co.id/checkout/T0001-REF', 'qr_string' => 'QR', 'pay_code' => null, 'total_fee' => 1500, 'expired_time' => 1800000000],
        ])]);

        $result = $this->driver()->createTransaction($this->request());

        Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Bearer api-key')
            && $request['method'] === 'QRIS'
            && $request['merchant_ref'] === 'INV1-REF'
            && $request['amount'] === 200000
            && $request['callback_url'] === 'https://isp.test/webhooks/payment/tripay'
            && $request['signature'] === hash_hmac('sha256', 'T0001INV1-REF200000', 'private-key'));
        $this->assertSame('T0001-REF', $result->gatewayRef);
        $this->assertSame('https://tripay.co.id/checkout/T0001-REF', $result->checkoutUrl);
        $this->assertSame('QR', $result->qrString);
        $this->assertSame(1500, $result->fee);
        $this->assertSame(1800000000, $result->expiresAt->timestamp);
    }

    public function test_uses_production_endpoint_outside_sandbox(): void
    {
        Http::fake(['tripay.co.id/api/transaction/create' => Http::response(['success' => true, 'data' => ['reference' => 'R']])]);

        $this->driver(sandbox: false)->createTransaction($this->request());

        Http::assertSentCount(1);
    }

    public function test_rejected_transaction_raises_gateway_exception_with_message(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Invalid payment method'], 400)]);

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessage('Invalid payment method');

        $this->driver()->createTransaction($this->request());
    }

    public function test_connection_failure_raises_gateway_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(PaymentGatewayException::class);

        $this->driver()->createTransaction($this->request());
    }

    public function test_accepts_callback_signed_over_raw_body(): void
    {
        $this->assertTrue($this->driver()->verifyCallback($this->callbackRequest(['merchant_ref' => 'INV1-REF', 'status' => 'PAID'])));
    }

    public function test_rejects_tampered_or_unsigned_callback(): void
    {
        $driver = $this->driver();

        $this->assertFalse($driver->verifyCallback($this->callbackRequest(['status' => 'PAID'], signature: hash_hmac('sha256', '{"status":"UNPAID"}', 'private-key'))));
        $this->assertFalse($driver->verifyCallback($this->callbackRequest(['status' => 'PAID'], signature: '')));
        $this->assertFalse($driver->verifyCallback($this->callbackRequest(['status' => 'PAID'], event: 'payout')));
    }

    /**
     * @return array<string, array{string, PaymentStatus}>
     */
    public static function statuses(): array
    {
        return [
            'paid' => ['PAID', PaymentStatus::Paid],
            'expired' => ['EXPIRED', PaymentStatus::Expired],
            'failed' => ['FAILED', PaymentStatus::Failed],
            'refund' => ['REFUND', PaymentStatus::Failed],
            'unpaid' => ['UNPAID', PaymentStatus::Pending],
        ];
    }

    #[DataProvider('statuses')]
    public function test_maps_callback_status(string $tripayStatus, PaymentStatus $expected): void
    {
        $this->assertSame($expected, $this->driver()->parseCallback($this->callbackRequest(['merchant_ref' => 'X', 'status' => $tripayStatus]))->status);
    }

    public function test_callback_amount_excludes_fee_charged_to_customer(): void
    {
        $result = $this->driver()->parseCallback($this->callbackRequest([
            'merchant_ref' => 'INV1-REF', 'reference' => 'T0001-REF', 'status' => 'PAID', 'total_amount' => 204250, 'fee_customer' => 4250,
        ]));

        $this->assertSame('INV1-REF', $result->merchantRef);
        $this->assertSame('T0001-REF', $result->gatewayRef);
        $this->assertSame(200000, $result->amount);
    }

    public function test_lists_active_channels(): void
    {
        Http::fake(['tripay.co.id/api-sandbox/merchant/payment-channel' => Http::response(['success' => true, 'data' => [
            ['code' => 'QRIS', 'name' => 'QRIS', 'active' => true],
            ['code' => 'MANDIRIVA', 'name' => 'Mandiri VA', 'active' => false],
        ]])]);

        $this->assertSame([['code' => 'QRIS', 'name' => 'QRIS']], $this->driver()->availableChannels());
    }
}
