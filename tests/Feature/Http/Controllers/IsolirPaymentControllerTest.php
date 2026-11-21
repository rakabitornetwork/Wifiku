<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsolirPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function activeTripay(array $channels = ['QRIS', 'BRIVA']): PaymentGateway
    {
        return PaymentGateway::factory()->create([
            'driver' => PaymentGatewayDriver::Tripay,
            'is_active' => true,
            'enabled_channels' => $channels,
            'credentials' => ['merchant_code' => 'T0001', 'api_key' => 'api-key', 'private_key' => 'private-key'],
        ]);
    }

    private function fakeTripay(): void
    {
        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => [
            'reference' => 'T0001-REF', 'checkout_url' => 'https://tripay.co.id/checkout/T0001-REF', 'expired_time' => now()->addDay()->timestamp,
        ]])]);
    }

    public function test_redirects_customer_to_gateway_checkout_for_outstanding_amount(): void
    {
        $this->activeTripay();
        $this->fakeTripay();
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->for($customer)->create(['total' => 200000, 'amount_paid' => 50000, 'status' => InvoiceStatus::Partial]);

        $this->post("/isolir/{$customer->customer_code}/pay", ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'])
            ->assertRedirect('https://tripay.co.id/checkout/T0001-REF');

        $payment = Payment::sole();
        $this->assertSame(150000, $payment->amount);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('QRIS', $payment->channel);
        Http::assertSent(fn (HttpRequest $request) => $request['amount'] === 150000
            && $request['callback_url'] === route('webhooks.payment', 'tripay')
            && $request['return_url'] === route('isolir.show', $customer->customer_code));
    }

    public function test_customer_without_whatsapp_sends_company_cs_number_to_gateway(): void
    {
        (new SettingService)->set('company', 'cs_whatsapp', '6281299990000');
        $this->activeTripay();
        $this->fakeTripay();
        $customer = Customer::factory()->create(['whatsapp' => '']);
        $invoice = Invoice::factory()->for($customer)->create(['total' => 200000, 'amount_paid' => 0, 'status' => InvoiceStatus::Unpaid]);

        $this->post("/isolir/{$customer->customer_code}/pay", ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'])->assertRedirect('https://tripay.co.id/checkout/T0001-REF');

        Http::assertSent(fn (HttpRequest $request) => $request['customer_phone'] === '6281299990000');
    }

    public function test_inertia_visit_gets_external_redirect_response(): void
    {
        $this->activeTripay();
        $this->fakeTripay();
        $invoice = Invoice::factory()->create();

        $this->withHeaders(['X-Inertia' => 'true'])
            ->post("/isolir/{$invoice->customer->customer_code}/pay", ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://tripay.co.id/checkout/T0001-REF');
    }

    public function test_reuses_still_valid_pending_checkout_instead_of_creating_another(): void
    {
        $this->activeTripay();
        $this->fakeTripay();
        $invoice = Invoice::factory()->create();
        $payload = ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'];

        $this->post("/isolir/{$invoice->customer->customer_code}/pay", $payload);
        $this->post("/isolir/{$invoice->customer->customer_code}/pay", $payload);

        Http::assertSentCount(1);
        $this->assertSame(1, Payment::count());
    }

    public function test_rejects_channel_not_enabled_for_the_active_gateway(): void
    {
        $this->activeTripay(['QRIS']);
        $invoice = Invoice::factory()->create();

        $this->post("/isolir/{$invoice->customer->customer_code}/pay", ['invoice_number' => $invoice->invoice_number, 'channel' => 'OVO'])
            ->assertSessionHasErrors('channel');

        Http::assertNothingSent();
    }

    public function test_cannot_pay_another_customers_invoice(): void
    {
        $this->activeTripay();
        $customer = Customer::factory()->create();
        $foreignInvoice = Invoice::factory()->create();

        $this->post("/isolir/{$customer->customer_code}/pay", ['invoice_number' => $foreignInvoice->invoice_number, 'channel' => 'QRIS'])->assertNotFound();
    }

    public function test_paid_invoice_cannot_be_paid_again(): void
    {
        $this->activeTripay();
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'amount_paid' => 200000, 'total' => 200000]);

        $this->post("/isolir/{$invoice->customer->customer_code}/pay", ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'])
            ->assertSessionHasErrors('invoice_number');
    }

    public function test_gateway_failure_marks_attempt_failed_and_returns_with_error(): void
    {
        $this->activeTripay();
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'down'], 500)]);
        $invoice = Invoice::factory()->create();

        $this->from("/isolir/{$invoice->customer->customer_code}")
            ->post("/isolir/{$invoice->customer->customer_code}/pay", ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'])
            ->assertRedirect("/isolir/{$invoice->customer->customer_code}");

        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
    }
}
