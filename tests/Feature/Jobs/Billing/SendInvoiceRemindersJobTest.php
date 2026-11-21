<?php

namespace Tests\Feature\Jobs\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\WhatsappMessageType;
use App\Jobs\Billing\SendInvoiceRemindersJob;
use App\Models\Invoice;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendInvoiceRemindersJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(): void
    {
        $this->app->call([new SendInvoiceRemindersJob, 'handle']);
    }

    public function test_reminds_unpaid_invoices_due_in_three_days_exactly_once(): void
    {
        $this->travelTo('2026-11-05 09:00');
        $due = Invoice::factory()->create(['due_date' => '2026-11-08', 'status' => InvoiceStatus::Unpaid]);
        Invoice::factory()->create(['due_date' => '2026-11-09', 'status' => InvoiceStatus::Unpaid]);
        Invoice::factory()->create(['due_date' => '2026-11-08', 'status' => InvoiceStatus::Paid]);

        $this->runJob();
        $this->runJob();

        $reminder = WhatsappMessage::where('type', WhatsappMessageType::Reminder)->sole();
        $this->assertSame($due->customer_id, $reminder->customer_id);
        $this->assertStringContainsString($due->invoice_number, $reminder->body);
        $this->assertNotNull($due->fresh()->reminder_sent_at);
    }

    public function test_partial_invoice_reminder_states_remaining_balance(): void
    {
        $this->travelTo('2026-11-05 09:00');
        Invoice::factory()->create(['due_date' => '2026-11-08', 'total' => 200000, 'amount_paid' => 50000, 'status' => InvoiceStatus::Partial]);

        $this->runJob();

        $this->assertStringContainsString('Rp 150.000', WhatsappMessage::where('type', WhatsappMessageType::Reminder)->sole()->body);
    }
}
