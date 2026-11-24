<?php

namespace Tests\Feature\Vpn;

use App\Actions\Payment\ApplyPayment;
use App\Actions\Payment\RecordCashPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGatewayDriver;
use App\Enums\VpnAccountStatus;
use App\Jobs\Billing\GenerateVpnInvoicesJob;
use App\Jobs\Billing\SendInvoiceRemindersJob;
use App\Jobs\Vpn\ExpireVpnAccountsJob;
use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\VpnAccount;
use App\Models\WhatsappMessage;
use App\Services\Settings\SettingService;
use App\Services\Vpn\VpnInvoiceGenerator;
use App\Services\Whatsapp\CustomerNotifier;
use App\Services\Whatsapp\VpnNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class VpnBillingTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->today = CarbonImmutable::today();
    }

    private function account(array $attributes = []): VpnAccount
    {
        return VpnAccount::factory()->create([
            'whatsapp' => '6281234567890',
            'monthly_price' => 50000,
            'expires_at' => $this->today->addDays(3)->toDateString(),
            ...$attributes,
        ]);
    }

    public function test_invoice_is_issued_inside_the_lead_window_for_the_next_month(): void
    {
        $account = $this->account(['expires_at' => $this->today->addDays(3)->toDateString()]);

        $invoice = app(VpnInvoiceGenerator::class)->generateFor($account, $this->today);

        $this->assertSame($account->id, $invoice->vpn_account_id);
        $this->assertNull($invoice->customer_id);
        $this->assertSame(50000, $invoice->total);
        $this->assertSame($this->today->addDays(4)->toDateString(), $invoice->period_start->toDateString());
        $this->assertSame($this->today->addDays(4)->addMonthNoOverflow()->subDay()->toDateString(), $invoice->period_end->toDateString());
        $this->assertSame($this->today->addDays(3)->toDateString(), $invoice->due_date->toDateString());
        $this->assertCount(1, $invoice->items);
    }

    public function test_invoice_is_not_issued_outside_the_window_or_for_unbillable_accounts(): void
    {
        $generator = app(VpnInvoiceGenerator::class);

        $this->assertNull($generator->generateFor($this->account(['expires_at' => $this->today->addDays(20)->toDateString()]), $this->today));
        $this->assertNull($generator->generateFor($this->account(['expires_at' => null]), $this->today));
        $this->assertNull($generator->generateFor($this->account(['monthly_price' => 0]), $this->today));
        $this->assertNull($generator->generateFor($this->account(['status' => VpnAccountStatus::Disabled]), $this->today));
        $this->assertSame(0, Invoice::count());
    }

    public function test_generating_twice_does_not_duplicate_the_invoice(): void
    {
        $account = $this->account();

        (new GenerateVpnInvoicesJob)->handle(app(VpnInvoiceGenerator::class));
        (new GenerateVpnInvoicesJob)->handle(app(VpnInvoiceGenerator::class));

        $this->assertSame(1, $account->invoices()->count());
    }

    public function test_expired_account_is_billed_from_today_and_cancelled_invoice_is_reissued(): void
    {
        $account = $this->account(['expires_at' => $this->today->subDays(10)->toDateString(), 'status' => VpnAccountStatus::Disabled, 'auto_disabled_at' => now()]);
        $generator = app(VpnInvoiceGenerator::class);

        $invoice = $generator->generateFor($account, $this->today);

        $this->assertSame($this->today->toDateString(), $invoice->period_start->toDateString());
        $this->assertSame($this->today->toDateString(), $invoice->due_date->toDateString());

        $invoice->update(['status' => InvoiceStatus::Cancelled]);
        $invoice->delete();

        $this->assertNotNull($generator->generateFor($account->fresh(), $this->today->addDay()));
    }

    public function test_issuing_an_invoice_sends_whatsapp_with_the_portal_link(): void
    {
        $account = $this->account();

        app(VpnInvoiceGenerator::class)->generateFor($account, $this->today);

        $message = WhatsappMessage::sole();
        $this->assertSame('6281234567890', $message->phone);
        $this->assertNull($message->customer_id);
        $this->assertStringContainsString(route('vpn-portal.show', $account->portal_token), $message->body);
    }

    public function test_cash_payment_extends_the_account_and_queues_a_sync(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $account = $this->account();
        $invoice = app(VpnInvoiceGenerator::class)->generateFor($account, $this->today);

        app(RecordCashPayment::class)->handle($invoice, 50000, $this->admin());

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame($invoice->period_end->toDateString(), $account->fresh()->expires_at->toDateString());
        Queue::assertPushed(SyncVpnAccountJob::class);
    }

    public function test_partial_payment_does_not_extend_the_account(): void
    {
        $account = $this->account();
        $before = $account->expires_at->toDateString();
        $invoice = app(VpnInvoiceGenerator::class)->generateFor($account, $this->today);

        app(RecordCashPayment::class)->handle($invoice, 20000, $this->admin());

        $this->assertSame(InvoiceStatus::Partial, $invoice->fresh()->status);
        $this->assertSame($before, $account->fresh()->expires_at->toDateString());
    }

    public function test_paying_reopens_an_account_that_expired_automatically(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $account = $this->account(['expires_at' => $this->today->subDay()->toDateString()]);
        (new ExpireVpnAccountsJob)->handle(app(VpnNotifier::class));
        $this->assertSame(VpnAccountStatus::Disabled, $account->fresh()->status);

        $invoice = app(VpnInvoiceGenerator::class)->generateFor($account->fresh(), $this->today);
        app(RecordCashPayment::class)->handle($invoice, 50000, $this->admin());

        $account->refresh();
        $this->assertSame(VpnAccountStatus::Active, $account->status);
        $this->assertNull($account->auto_disabled_at);
        $this->assertTrue($account->expires_at->greaterThan($this->today));
    }

    public function test_paying_does_not_reopen_an_account_the_admin_disabled(): void
    {
        $account = $this->account(['status' => VpnAccountStatus::Disabled]);
        $invoice = Invoice::factory()->create(['customer_id' => null, 'vpn_account_id' => $account->id, 'total' => 50000, 'period_start' => $this->today->addDays(4)]);

        app(RecordCashPayment::class)->handle($invoice, 50000, $this->admin());

        $this->assertSame(VpnAccountStatus::Disabled, $account->fresh()->status);
    }

    public function test_public_portal_lists_open_invoices_without_login(): void
    {
        $account = $this->account(['name' => 'Budi Santoso']);
        app(VpnInvoiceGenerator::class)->generateFor($account, $this->today);

        $this->get("/layanan-vpn/{$account->portal_token}")->assertInertia(
            fn (Assert $page) => $page->component('Public/VpnPortal')
                ->where('account.name', 'Budi S***')
                ->has('invoices', 1)
                ->where('outstanding', 50000)
                ->missing('account.password'),
        );

        $this->get('/layanan-vpn/token-ngawur')->assertNotFound();
    }

    public function test_portal_cannot_pay_an_invoice_of_another_account(): void
    {
        $account = $this->account();
        $other = $this->account();
        $invoice = app(VpnInvoiceGenerator::class)->generateFor($other, $this->today);

        $this->post("/layanan-vpn/{$account->portal_token}/pay", ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'])->assertNotFound();
    }

    public function test_invoice_list_shows_vpn_invoices(): void
    {
        $account = $this->account(['name' => 'Budi VPN']);
        app(VpnInvoiceGenerator::class)->generateFor($account, $this->today);

        $this->actingAs($this->admin())->get('/invoices?search=Budi')->assertInertia(
            fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.vpn_account.name', 'Budi VPN')->where('invoices.data.0.customer', null),
        );
    }

    public function test_reminder_job_reaches_vpn_invoices(): void
    {
        $account = $this->account(['expires_at' => $this->today->addDays(3)->toDateString()]);
        $invoice = app(VpnInvoiceGenerator::class)->generateFor($account, $this->today);
        WhatsappMessage::query()->delete();

        (new SendInvoiceRemindersJob)->handle(app(SettingService::class), app(CustomerNotifier::class));

        $this->assertSame(1, WhatsappMessage::count());
        $this->assertNotNull($invoice->fresh()->reminder_sent_at);
    }

    public function test_online_checkout_for_vpn_invoice_then_gateway_payment_extends_the_account(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        PaymentGateway::factory()->create([
            'driver' => PaymentGatewayDriver::Tripay,
            'is_active' => true,
            'enabled_channels' => ['QRIS'],
            'credentials' => ['merchant_code' => 'T0001', 'api_key' => 'api-key', 'private_key' => 'private-key'],
        ]);
        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => [
            'reference' => 'T0001-REF', 'checkout_url' => 'https://tripay.co.id/checkout/T0001-REF', 'expired_time' => now()->addDay()->timestamp,
        ]])]);
        $account = $this->account();
        $invoice = app(VpnInvoiceGenerator::class)->generateFor($account, $this->today);

        $this->post("/layanan-vpn/{$account->portal_token}/pay", ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'])
            ->assertRedirect('https://tripay.co.id/checkout/T0001-REF');

        $payment = Payment::sole();
        $this->assertNull($payment->customer_id);
        $this->assertSame(50000, $payment->amount);

        app(ApplyPayment::class)->handle($payment, 'T0001-REF');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame($invoice->period_end->toDateString(), $account->fresh()->expires_at->toDateString());
    }
}
