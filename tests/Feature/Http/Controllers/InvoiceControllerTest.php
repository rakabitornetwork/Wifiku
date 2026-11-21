<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Jobs\Billing\GenerateMonthlyInvoicesJob;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_lists_invoices_filtered_by_status_and_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi Santoso']);
        Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Unpaid]);
        Invoice::factory()->for($customer)->create(['period_start' => '2026-09-01', 'status' => InvoiceStatus::Paid]);
        Invoice::factory()->for(Customer::factory()->create(['name' => 'Siti Rahayu']))->create(['status' => InvoiceStatus::Unpaid]);

        $this->actingAs($this->admin())->get('/invoices?status=unpaid&search=Budi')->assertInertia(
            fn (Assert $page) => $page->component('Invoices/Index')->has('invoices.data', 1)->where('invoices.data.0.customer.name', 'Budi Santoso'),
        );
    }

    public function test_cashier_records_cash_payment(): void
    {
        $admin = $this->admin();
        $invoice = Invoice::factory()->create(['total' => 200000]);

        $this->actingAs($admin)->post("/invoices/{$invoice->id}/payments", ['amount' => 200000])->assertSessionDoesntHaveErrors();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame($admin->id, $invoice->payments()->sole()->received_by);
    }

    public function test_cash_payment_above_outstanding_is_rejected(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000]);

        $this->actingAs($this->admin())->post("/invoices/{$invoice->id}/payments", ['amount' => 250000])->assertSessionHasErrors('amount');
    }

    public function test_technician_cannot_record_payments_or_generate_invoices(): void
    {
        $technician = $this->userWithRole('Teknisi');
        $invoice = Invoice::factory()->create();

        $this->actingAs($technician)->post("/invoices/{$invoice->id}/payments", ['amount' => 1000])->assertForbidden();
        $this->actingAs($technician)->post('/invoices/generate')->assertForbidden();
    }

    public function test_generate_button_queues_the_daily_generator(): void
    {
        Queue::fake();

        $this->actingAs($this->admin())->post('/invoices/generate')->assertRedirect();

        Queue::assertPushed(GenerateMonthlyInvoicesJob::class);
    }
}
