<?php

namespace Tests\Feature\Drivers\Payment;

use App\Drivers\Payment\Data\TransactionRequest;
use App\Drivers\Payment\DuitkuDriver;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentStatus;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuitkuDriverTest extends TestCase
{
    use RefreshDatabase;

    private function driver(): DuitkuDriver
    {
        return new DuitkuDriver(PaymentGateway::factory()->create([
            'driver' => PaymentGatewayDriver::Duitku,
            'credentials' => ['merchant_code' => 'D0001', 'api_key' => 'duitku-key'],
        ]));
    }

    private function callbackRequest(array $overrides = []): Request
    {
        $fields = ['merchantCode' => 'D0001', 'amount' => '200000', 'merchantOrderId' => 'INV1-REF', 'resultCode' => '00', 'reference' => 'DK-REF', ...$overrides];
        $fields['signature'] ??= md5($fields['merchantCode'].$fields['amount'].$fields['merchantOrderId'].'duitku-key');

        return Request::create('/webhooks/payment/duitku', 'POST', $fields);
    }

    public function test_creates_signed_inquiry(): void
    {
        Http::fake(['sandbox.duitku.com/webapi/api/merchant/v2/inquiry' => Http::response([
            'merchantCode' => 'D0001', 'reference' => 'DK-REF', 'paymentUrl' => 'https://sandbox.duitku.com/pay/DK-REF', 'vaNumber' => '7007', 'statusCode' => '00', 'statusMessage' => 'SUCCESS',
        ])]);

        $result = $this->driver()->createTransaction(new TransactionRequest(
            merchantRef: 'INV1-REF', amount: 200000, channel: 'BR', customerName: 'Budi Santoso Wijaya Kusuma', customerPhone: '6281',
            description: 'Tagihan', callbackUrl: 'https://isp.test/cb', returnUrl: 'https://isp.test/ret', customerEmail: 'a@b.test', expiresInMinutes: 60,
        ));

        Http::assertSent(fn (HttpRequest $request) => $request['merchantCode'] === 'D0001'
            && $request['paymentMethod'] === 'BR'
            && $request['paymentAmount'] === 200000
            && $request['expiryPeriod'] === 60
            && mb_strlen($request['customerVaName']) <= 20
            && $request['signature'] === md5('D0001INV1-REF200000duitku-key'));
        $this->assertSame('DK-REF', $result->gatewayRef);
        $this->assertSame('https://sandbox.duitku.com/pay/DK-REF', $result->checkoutUrl);
        $this->assertSame('7007', $result->vaNumber);
    }

    public function test_non_success_status_code_raises_exception(): void
    {
        Http::fake(['*' => Http::response(['statusCode' => '01', 'statusMessage' => 'Payment channel not available'])]);

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessage('Payment channel not available');

        $this->driver()->createTransaction(new TransactionRequest('R', 1000, 'XX', 'A', '62', 'D', 'https://c', 'https://r'));
    }

    public function test_verifies_callback_signature_and_merchant_code(): void
    {
        $driver = $this->driver();

        $this->assertTrue($driver->verifyCallback($this->callbackRequest()));
        $this->assertFalse($driver->verifyCallback($this->callbackRequest(['signature' => md5('tebak')])));
        $this->assertFalse($driver->verifyCallback($this->callbackRequest(['amount' => '1'] + ['signature' => md5('D0001200000INV1-REFduitku-key')])));
        $this->assertFalse($driver->verifyCallback($this->callbackRequest(['merchantCode' => 'LAIN'])));
    }

    public function test_maps_result_codes_to_payment_status(): void
    {
        $driver = $this->driver();

        $paid = $driver->parseCallback($this->callbackRequest());
        $this->assertSame(PaymentStatus::Paid, $paid->status);
        $this->assertSame(200000, $paid->amount);
        $this->assertSame('INV1-REF', $paid->merchantRef);
        $this->assertSame('DK-REF', $paid->gatewayRef);
        $this->assertSame(PaymentStatus::Failed, $driver->parseCallback($this->callbackRequest(['resultCode' => '01']))->status);
    }
}
