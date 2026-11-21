<?php

namespace Tests\Feature\Drivers\Payment;

use App\Drivers\Payment\Data\TransactionRequest;
use App\Drivers\Payment\MidtransDriver;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentStatus;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MidtransDriverTest extends TestCase
{
    use RefreshDatabase;

    private function driver(): MidtransDriver
    {
        return new MidtransDriver(PaymentGateway::factory()->create([
            'driver' => PaymentGatewayDriver::Midtrans,
            'credentials' => ['server_key' => 'SB-Mid-server-xyz', 'client_key' => 'SB-Mid-client-xyz'],
        ]));
    }

    private function notification(array $overrides = []): Request
    {
        $payload = ['order_id' => 'INV1-REF', 'status_code' => '200', 'gross_amount' => '200000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'mid-tx-1', ...$overrides];
        $payload['signature_key'] ??= hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-server-xyz');

        return Request::create('/webhooks/payment/midtrans', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload));
    }

    public function test_creates_snap_transaction_with_notification_override(): void
    {
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'tok', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/tok'], 201)]);

        $result = $this->driver()->createTransaction(new TransactionRequest(
            merchantRef: 'INV1-REF', amount: 200000, channel: 'bri_va', customerName: 'Budi', customerPhone: '6281',
            description: 'Tagihan', callbackUrl: 'https://isp.test/webhooks/payment/midtrans', returnUrl: 'https://isp.test/isolir/X',
        ));

        Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-xyz:'))
            && $request->hasHeader('X-Override-Notification', 'https://isp.test/webhooks/payment/midtrans')
            && $request['transaction_details'] === ['order_id' => 'INV1-REF', 'gross_amount' => 200000]
            && $request['enabled_payments'] === ['bri_va']);
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v4/redirection/tok', $result->checkoutUrl);
        $this->assertNull($result->gatewayRef);
    }

    public function test_verifies_sha512_signature(): void
    {
        $driver = $this->driver();

        $this->assertTrue($driver->verifyCallback($this->notification()));
        $this->assertFalse($driver->verifyCallback($this->notification(['signature_key' => hash('sha512', 'tebak')])));
        $this->assertFalse($driver->verifyCallback($this->notification(['gross_amount' => '1.00', 'signature_key' => hash('sha512', 'INV1-REF200200000.00SB-Mid-server-xyz')])));
    }

    /**
     * @return array<string, array{string, string|null, PaymentStatus}>
     */
    public static function statuses(): array
    {
        return [
            'settlement' => ['settlement', null, PaymentStatus::Paid],
            'capture accepted' => ['capture', 'accept', PaymentStatus::Paid],
            'capture challenged' => ['capture', 'challenge', PaymentStatus::Pending],
            'pending' => ['pending', null, PaymentStatus::Pending],
            'expire' => ['expire', null, PaymentStatus::Expired],
            'deny' => ['deny', null, PaymentStatus::Failed],
            'cancel' => ['cancel', null, PaymentStatus::Failed],
        ];
    }

    #[DataProvider('statuses')]
    public function test_maps_transaction_status(string $status, ?string $fraud, PaymentStatus $expected): void
    {
        $result = $this->driver()->parseCallback($this->notification(['transaction_status' => $status, 'fraud_status' => $fraud]));

        $this->assertSame($expected, $result->status);
        $this->assertSame(200000, $result->amount);
        $this->assertSame('mid-tx-1', $result->gatewayRef);
    }
}
