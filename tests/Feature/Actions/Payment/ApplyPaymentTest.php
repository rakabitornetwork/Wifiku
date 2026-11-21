<?php

namespace Tests\Feature\Actions\Payment;

use App\Actions\Payment\ApplyPayment;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\WhatsappMessageType;
use App\Jobs\Network\ReopenCustomerJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApplyPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function pendingPayment(Invoice $invoice, int $amount): Payment
    {
        return Payment::factory()->for($invoice)->create(['customer_id' => $invoice->customer_id, 'amount' => $amount, 'status' => PaymentStatus::Pending]);
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000]);
        $payment = $this->pendingPayment($invoice, 200000);

        $this->assertTrue($this->app->make(ApplyPayment::class)->handle($payment, 'T-REF-1'));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(200000, $invoice->amount_paid);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame('T-REF-1', $payment->fresh()->gateway_ref);
    }

    public function test_partial_payment_marks_invoice_partial(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000]);

        $this->app->make(ApplyPayment::class)->handle($this->pendingPayment($invoice, 50000));

        $this->assertSame(InvoiceStatus::Partial, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
    }

    public function test_applying_the_same_payment_twice_counts_it_once(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000]);
        $payment = $this->pendingPayment($invoice, 100000);

        $this->app->make(ApplyPayment::class)->handle($payment);
        $this->assertFalse($this->app->make(ApplyPayment::class)->handle(Payment::find($payment->id)));

        $this->assertSame(100000, $invoice->fresh()->amount_paid);
        $this->assertSame(1, $invoice->customer->whatsappMessages()->where('type', WhatsappMessageType::Receipt)->count());
    }

    public function test_sends_receipt_to_customer(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000]);

        $this->app->make(ApplyPayment::class)->handle($this->pendingPayment($invoice, 200000));

        $receipt = $invoice->customer->whatsappMessages()->where('type', WhatsappMessageType::Receipt)->sole();
        $this->assertStringContainsString('LUNAS', $receipt->body);
        $this->assertStringContainsString($invoice->invoice_number, $receipt->body);
    }

    public function test_reopens_isolated_customer_once_no_overdue_invoices_remain(): void
    {
        Queue::fake([ReopenCustomerJob::class]);
        $customer = Customer::factory()->create(['status' => CustomerStatus::Isolated]);
        $invoice = Invoice::factory()->for($customer)->create(['total' => 200000, 'due_date' => now()->subDays(5)]);

        $this->app->make(ApplyPayment::class)->handle($this->pendingPayment($invoice, 200000));

        Queue::assertPushed(ReopenCustomerJob::class, fn (ReopenCustomerJob $job) => $job->customer->is($customer));
    }

    public function test_keeps_customer_isolated_while_another_overdue_invoice_is_unpaid(): void
    {
        Queue::fake([ReopenCustomerJob::class]);
        $customer = Customer::factory()->create(['status' => CustomerStatus::Isolated]);
        Invoice::factory()->for($customer)->create(['period_start' => '2026-09-01', 'due_date' => now()->subMonth(), 'status' => InvoiceStatus::Unpaid]);
        $invoice = Invoice::factory()->for($customer)->create(['period_start' => '2026-10-01', 'total' => 200000, 'due_date' => now()->subDays(5)]);

        $this->app->make(ApplyPayment::class)->handle($this->pendingPayment($invoice, 200000));

        Queue::assertNotPushed(ReopenCustomerJob::class);
    }
}
