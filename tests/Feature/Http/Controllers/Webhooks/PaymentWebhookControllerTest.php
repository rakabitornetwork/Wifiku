<?php

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use FakesRouterOs;
    use RefreshDatabase;

    private PaymentGateway $tripay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tripay = PaymentGateway::factory()->create([
            'driver' => PaymentGatewayDriver::Tripay,
            'is_active' => true,
            'credentials' => ['merchant_code' => 'T0001', 'api_key' => 'api-key', 'private_key' => 'private-key'],
        ]);
    }

    private function pendingPayment(int $amount = 200000, ?Invoice $invoice = null): Payment
    {
        $invoice ??= Invoice::factory()->create(['total' => $amount]);

        return Payment::factory()->for($invoice)->create([
            'customer_id' => $invoice->customer_id,
            'payment_gateway_id' => $this->tripay->id,
            'method' => PaymentMethod::Gateway,
            'merchant_ref' => 'INV'.$invoice->id.'-REF',
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
        ]);
    }

    private function postTripay(array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', '/webhooks/payment/tripay', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, 'private-key'),
            'HTTP_X_CALLBACK_EVENT' => 'payment_status',
        ], content: $body);
    }

    private function paidPayload(Payment $payment, array $overrides = []): array
    {
        return ['merchant_ref' => $payment->merchant_ref, 'reference' => 'T0001-REF', 'status' => 'PAID', 'total_amount' => $payment->amount, 'fee_customer' => 0, ...$overrides];
    }

    public function test_valid_paid_callback_settles_invoice_without_csrf_token(): void
    {
        $payment = $this->pendingPayment();

        $this->postTripay($this->paidPayload($payment))->assertOk()->assertJson(['success' => true]);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame('T0001-REF', $payment->fresh()->gateway_ref);
        $this->assertSame(InvoiceStatus::Paid, $payment->invoice->fresh()->status);
        $callback = PaymentCallback::sole();
        $this->assertTrue($callback->signature_valid);
        $this->assertNotNull($callback->processed_at);
        $this->assertNull($callback->error);
    }

    public function test_invalid_signature_is_logged_and_changes_nothing(): void
    {
        $payment = $this->pendingPayment();

        $this->postTripay($this->paidPayload($payment), signature: 'palsu')->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $callback = PaymentCallback::sole();
        $this->assertFalse($callback->signature_valid);
        $this->assertSame($payment->merchant_ref, $callback->merchant_ref);
    }

    public function test_repeated_callback_is_applied_only_once(): void
    {
        $payment = $this->pendingPayment(100000, Invoice::factory()->create(['total' => 300000]));

        $this->postTripay($this->paidPayload($payment))->assertOk();
        $this->postTripay($this->paidPayload($payment))->assertOk();

        $this->assertSame(100000, $payment->invoice->fresh()->amount_paid);
        $this->assertSame(2, PaymentCallback::count());
    }

    public function test_amount_mismatch_is_recorded_and_not_applied(): void
    {
        $payment = $this->pendingPayment();

        $this->postTripay($this->paidPayload($payment, ['total_amount' => 1000]))->assertOk();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertStringContainsString('Nominal', PaymentCallback::sole()->error);
    }

    public function test_unknown_reference_is_recorded(): void
    {
        $this->postTripay(['merchant_ref' => 'TIDAK-ADA', 'status' => 'PAID', 'total_amount' => 1])->assertOk();

        $this->assertStringContainsString('tidak ditemukan', PaymentCallback::sole()->error);
    }

    public function test_callback_cannot_settle_payment_created_on_another_gateway(): void
    {
        $payment = $this->pendingPayment();
        $payment->update(['payment_gateway_id' => PaymentGateway::factory()->create(['driver' => PaymentGatewayDriver::Duitku])->id]);

        $this->postTripay($this->paidPayload($payment))->assertOk();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_expired_callback_closes_pending_payment_but_never_a_paid_one(): void
    {
        $pending = $this->pendingPayment();
        $paid = $this->pendingPayment();
        $paid->update(['status' => PaymentStatus::Paid]);

        $this->postTripay($this->paidPayload($pending, ['status' => 'EXPIRED']))->assertOk();
        $this->postTripay($this->paidPayload($paid, ['status' => 'EXPIRED']))->assertOk();

        $this->assertSame(PaymentStatus::Expired, $pending->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $paid->fresh()->status);
    }

    public function test_paying_overdue_invoice_reopens_isolated_customer_on_router(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Isolated, 'pppoe_username' => 'budi']);
        $fake = $this->fakeRouterOs($customer->router);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => 'ISOLIR']);
        $invoice = Invoice::factory()->for($customer)->create(['total' => 200000, 'due_date' => now()->subDays(3)]);
        $payment = $this->pendingPayment(200000, $invoice);

        $this->postTripay($this->paidPayload($payment))->assertOk();

        $this->assertSame(CustomerStatus::Active, $customer->fresh()->status);
        $this->assertSame($customer->package->mikrotik_profile, $fake->rows('/ppp/secret')[0]['profile']);
    }

    public function test_duitku_form_callback_is_supported(): void
    {
        $duitku = PaymentGateway::factory()->create(['driver' => PaymentGatewayDriver::Duitku, 'credentials' => ['merchant_code' => 'D0001', 'api_key' => 'k']]);
        $payment = $this->pendingPayment();
        $payment->update(['payment_gateway_id' => $duitku->id]);

        $this->post('/webhooks/payment/duitku', [
            'merchantCode' => 'D0001', 'amount' => '200000', 'merchantOrderId' => $payment->merchant_ref, 'resultCode' => '00', 'reference' => 'DK1',
            'signature' => md5('D0001200000'.$payment->merchant_ref.'k'),
        ])->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_gateway_without_credentials_rejects_callbacks(): void
    {
        PaymentGateway::factory()->create(['driver' => PaymentGatewayDriver::Midtrans, 'credentials' => null]);

        $this->postJson('/webhooks/payment/midtrans', ['order_id' => 'X', 'signature_key' => 'y'])->assertForbidden();
    }

    public function test_unknown_driver_returns_404(): void
    {
        $this->postJson('/webhooks/payment/paypal', [])->assertNotFound();
    }
}
