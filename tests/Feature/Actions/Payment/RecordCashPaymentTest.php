<?php

namespace Tests\Feature\Actions\Payment;

use App\Actions\Payment\RecordCashPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecordCashPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_paid_cash_payment_received_by_cashier(): void
    {
        $cashier = User::factory()->create();
        $invoice = Invoice::factory()->create(['total' => 200000]);

        $payment = $this->app->make(RecordCashPayment::class)->handle($invoice, 200000, $cashier);

        $this->assertSame(PaymentMethod::Cash, $payment->method);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame($cashier->id, $payment->received_by);
        $this->assertStringStartsWith('CASH-', $payment->merchant_ref);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_rejects_amount_above_outstanding_balance(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'amount_paid' => 150000, 'status' => InvoiceStatus::Partial]);

        $this->expectException(ValidationException::class);

        $this->app->make(RecordCashPayment::class)->handle($invoice, 60000, User::factory()->create());
    }

    /**
     * @return array<string, array{InvoiceStatus}>
     */
    public static function closedStatuses(): array
    {
        return ['paid' => [InvoiceStatus::Paid], 'cancelled' => [InvoiceStatus::Cancelled]];
    }

    #[DataProvider('closedStatuses')]
    public function test_rejects_payment_for_closed_invoice(InvoiceStatus $status): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => $status]);

        $this->expectException(ValidationException::class);

        $this->app->make(RecordCashPayment::class)->handle($invoice, 1000, User::factory()->create());
    }
}
