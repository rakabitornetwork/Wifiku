<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Jobs\Billing\SendDueDateRemindersJob;
use App\Jobs\Notification\SendTelegramCustomerMessageJob;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\Payment;
use App\Models\TelegramMessage;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DueDateReminderTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function invoice(array $invoice = [], array $customer = []): Invoice
    {
        $owner = Customer::factory()->create(['name' => 'Budi', 'customer_code' => 'TT-0001-0001', 'whatsapp' => '6281234567890', ...$customer]);

        return Invoice::factory()->create(['customer_id' => $owner->id, 'total' => 150000, 'amount_paid' => 50000, 'status' => InvoiceStatus::Partial, 'due_date' => now(), ...$invoice]);
    }

    public function test_an_invoice_due_today_is_reminded_once_on_whatsapp_and_telegram_with_the_remaining_amount(): void
    {
        Queue::fake();
        $settings = new SettingService;
        $settings->set('telegram', 'bot_enabled', '1');
        $settings->set('telegram', 'bot_token', '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ', encrypted: true);
        ChatSession::create(['channel' => 'telegram', 'sender_id' => '777', 'phone' => '6281234567890']);
        $invoice = $this->invoice(['invoice_number' => 'INV/2026/00001']);

        app()->call([new SendDueDateRemindersJob, 'handle']);
        app()->call([new SendDueDateRemindersJob, 'handle']);

        $message = WhatsappMessage::sole();
        $this->assertStringContainsString('INV/2026/00001 sebesar Rp 100.000 jatuh tempo HARI INI', $message->body);
        $this->assertStringContainsString('TT-0001-0001', $message->body);
        $this->assertStringContainsString(route('isolir.show', 'TT-0001-0001'), $message->body);
        Queue::assertPushed(SendTelegramCustomerMessageJob::class, 1);
        $telegram = TelegramMessage::sole();
        $this->assertStringStartsWith('⏳ Halo Budi', $telegram->body);
        $this->assertSame('777', $telegram->chat_id);
        $this->assertSame('reminder', $telegram->type);
        $this->assertNotNull($invoice->fresh()->due_reminder_sent_at);
        $this->assertNull($invoice->fresh()->reminder_sent_at);
    }

    public function test_only_unpaid_invoices_due_today_for_customers_not_under_a_payment_promise_are_reminded(): void
    {
        Queue::fake();
        $paid = $this->invoice(['status' => InvoiceStatus::Paid, 'amount_paid' => 150000], ['customer_code' => 'TT-A']);
        $tomorrow = $this->invoice(['due_date' => now()->addDay(), 'period_start' => now()->subMonth()->startOfMonth()], ['customer_code' => 'TT-B']);
        $yesterday = $this->invoice(['due_date' => now()->subDay(), 'period_start' => now()->subMonths(2)->startOfMonth()], ['customer_code' => 'TT-C']);
        $promised = $this->invoice(['period_start' => now()->subMonths(3)->startOfMonth()], ['customer_code' => 'TT-D', 'promise_to_pay_until' => now()->addDays(3)]);
        $cancelled = $this->invoice(['status' => InvoiceStatus::Cancelled, 'period_start' => now()->subMonths(4)->startOfMonth()], ['customer_code' => 'TT-E']);

        app()->call([new SendDueDateRemindersJob, 'handle']);

        $this->assertSame(0, WhatsappMessage::count());
        $this->assertNotNull($promised->fresh()->due_reminder_sent_at);
        $this->assertNull($paid->fresh()->due_reminder_sent_at);
        $this->assertNull($tomorrow->fresh()->due_reminder_sent_at);
        $this->assertNull($yesterday->fresh()->due_reminder_sent_at);
        $this->assertNull($cancelled->fresh()->due_reminder_sent_at);
    }

    public function test_message_type_switches_turn_off_only_their_own_message_on_every_channel(): void
    {
        Queue::fake();
        $settings = new SettingService;
        $settings->set('telegram', 'bot_enabled', '1');
        $settings->set('telegram', 'bot_token', '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ', encrypted: true);
        ChatSession::create(['channel' => 'telegram', 'sender_id' => '777', 'phone' => '6281234567890']);
        $invoice = $this->invoice();
        $customer = $invoice->customer;
        $notifier = app(CustomerNotifier::class);
        $settings->set('notify', 'due_today', '0');
        $settings->set('notify', 'isolated', '0');

        $notifier->invoiceDueToday($invoice);
        $notifier->isolated($customer);
        $this->assertSame(0, WhatsappMessage::count());
        Queue::assertNotPushed(SendTelegramCustomerMessageJob::class);

        $notifier->reopened($customer);
        $notifier->invoiceReminder($invoice);
        $notifier->paymentReceived(Payment::factory()->create(['invoice_id' => $invoice->id, 'paid_at' => now(), 'amount' => 50000]));
        $this->assertSame(3, WhatsappMessage::count());
        Queue::assertPushed(SendTelegramCustomerMessageJob::class, 3);
    }

    public function test_each_switch_controls_exactly_its_message_and_the_otp_is_never_switched_off(): void
    {
        Queue::fake();
        $settings = new SettingService;
        $invoice = $this->invoice();
        $customer = $invoice->customer;
        $notifier = app(CustomerNotifier::class);

        foreach (['invoice', 'reminder', 'receipt', 'isolated', 'reopened', 'extension', 'outage'] as $topic) {
            $settings->set('notify', $topic, '0');
        }

        $notifier->invoiceIssued($invoice);
        $notifier->invoiceReminder($invoice);
        $notifier->isolated($customer);
        $notifier->reopened($customer);
        $notifier->extensionGranted($customer, now()->addDays(3), 100000);
        $notifier->extensionEnding($customer, 100000);
        $notifier->outage($customer, Outage::factory()->create());
        $notifier->outageResolved($customer, Outage::factory()->create());
        $this->assertSame(0, WhatsappMessage::count());

        $notifier->loginCode('6281234567890', '123456', 10);
        $this->assertSame(1, WhatsappMessage::count());
    }

    public function test_the_notifications_page_lists_every_message_type_with_its_switch(): void
    {
        (new SettingService)->set('notify', 'due_today', '0');

        $this->actingAs($this->owner())->get('/settings/notifications')->assertInertia(fn (Assert $page) => $page
            ->where('groups.2.group', 'notify')
            ->has('groups.2.fields', 9)
            ->where('groups.2.fields.3.name', 'notify.due_today')
            ->where('groups.2.fields.3.value', '0'));

        $this->actingAs($this->owner())->put('/settings/integrations', ['settings' => ['notify' => ['invoice' => '0', 'due_today' => '1']]])->assertSessionDoesntHaveErrors();

        $this->assertSame('0', (new SettingService)->get('notify', 'invoice'));
        $this->assertSame('1', (new SettingService)->get('notify', 'due_today'));
    }
}
