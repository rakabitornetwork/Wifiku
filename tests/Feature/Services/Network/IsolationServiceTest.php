<?php

namespace Tests\Feature\Services\Network;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\WhatsappMessageType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Network\IsolationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class IsolationServiceTest extends TestCase
{
    use FakesRouterOs;
    use RefreshDatabase;

    private function service(): IsolationService
    {
        return $this->app->make(IsolationService::class);
    }

    private function customerWithInvoiceDue(string $dueDate, InvoiceStatus $status = InvoiceStatus::Unpaid, array $attributes = []): Customer
    {
        $customer = Customer::factory()->create($attributes);
        Invoice::factory()->for($customer)->create(['due_date' => $dueDate, 'status' => $status]);

        return $customer;
    }

    public function test_overdue_unpaid_invoice_requires_isolation(): void
    {
        $this->assertTrue($this->service()->shouldIsolate($this->customerWithInvoiceDue('2026-11-14'), CarbonImmutable::parse('2026-11-15')));
    }

    public function test_invoice_due_today_is_not_yet_overdue(): void
    {
        $this->assertFalse($this->service()->shouldIsolate($this->customerWithInvoiceDue('2026-11-15'), CarbonImmutable::parse('2026-11-15')));
    }

    public function test_partially_paid_overdue_invoice_still_requires_isolation(): void
    {
        $this->assertTrue($this->service()->shouldIsolate($this->customerWithInvoiceDue('2026-11-01', InvoiceStatus::Partial), CarbonImmutable::parse('2026-11-15')));
    }

    public function test_paid_or_cancelled_invoices_never_require_isolation(): void
    {
        $today = CarbonImmutable::parse('2026-11-15');

        $this->assertFalse($this->service()->shouldIsolate($this->customerWithInvoiceDue('2026-11-01', InvoiceStatus::Paid), $today));
        $this->assertFalse($this->service()->shouldIsolate($this->customerWithInvoiceDue('2026-11-01', InvoiceStatus::Cancelled), $today));
    }

    public function test_promise_to_pay_postpones_isolation_through_the_promised_day(): void
    {
        $customer = $this->customerWithInvoiceDue('2026-11-01', attributes: ['promise_to_pay_until' => '2026-11-20']);

        $this->assertFalse($this->service()->shouldIsolate($customer, CarbonImmutable::parse('2026-11-20')));
        $this->assertTrue($this->service()->shouldIsolate($customer, CarbonImmutable::parse('2026-11-21')));
    }

    public function test_only_active_customers_are_isolated(): void
    {
        $today = CarbonImmutable::parse('2026-11-15');

        $this->assertFalse($this->service()->shouldIsolate($this->customerWithInvoiceDue('2026-11-01', attributes: ['status' => CustomerStatus::Isolated]), $today));
        $this->assertFalse($this->service()->shouldIsolate($this->customerWithInvoiceDue('2026-11-01', attributes: ['status' => CustomerStatus::Inactive]), $today));
    }

    public function test_isolated_customer_can_reopen_when_paid_up_or_under_promise(): void
    {
        $today = CarbonImmutable::parse('2026-11-15');

        $this->assertTrue($this->service()->canReopen(Customer::factory()->create(['status' => CustomerStatus::Isolated]), $today));
        $this->assertTrue($this->service()->canReopen($this->customerWithInvoiceDue('2026-11-01', attributes: ['status' => CustomerStatus::Isolated, 'promise_to_pay_until' => '2026-11-15']), $today));
        $this->assertFalse($this->service()->canReopen($this->customerWithInvoiceDue('2026-11-01', attributes: ['status' => CustomerStatus::Isolated]), $today));
        $this->assertFalse($this->service()->canReopen(Customer::factory()->create(['status' => CustomerStatus::Active]), $today));
    }

    public function test_isolate_switches_router_profile_kicks_session_and_updates_status(): void
    {
        $router = Router::factory()->create(['isolir_profile' => 'ISOLIR']);
        $fake = $this->fakeRouterOs($router);
        $customer = Customer::factory()->for($router)->create(['pppoe_username' => 'budi']);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => 'HOME-20']);
        $fake->seed('/ppp/active', ['name' => 'budi']);

        $this->service()->isolate($customer);

        $this->assertSame('ISOLIR', $fake->rows('/ppp/secret')[0]['profile']);
        $this->assertSame([], $fake->rows('/ppp/active'));
        $this->assertSame(CustomerStatus::Isolated, $customer->fresh()->status);
        $this->assertNotNull($customer->fresh()->isolated_at);
        $this->assertSame(WhatsappMessageType::Isolir, $customer->whatsappMessages()->sole()->type);
    }

    public function test_failed_router_call_leaves_customer_status_unchanged(): void
    {
        $router = Router::factory()->create();
        $this->fakeRouterOs($router)->failWith = new MikrotikConnectionException('timeout');
        $customer = Customer::factory()->for($router)->create();

        try {
            $this->service()->isolate($customer);
            $this->fail('Expected the router failure to propagate so the job retries.');
        } catch (MikrotikConnectionException) {
            // diharapkan
        }

        $this->assertSame(CustomerStatus::Active, $customer->fresh()->status);
        $this->assertSame(0, $customer->whatsappMessages()->count());
    }

    public function test_reopen_restores_package_profile_and_status(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $customer = Customer::factory()->for($router)->for(Package::factory()->create(['mikrotik_profile' => 'HOME-20']))
            ->create(['pppoe_username' => 'budi', 'status' => CustomerStatus::Isolated, 'isolated_at' => now()]);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => 'ISOLIR']);

        $this->service()->reopen($customer);

        $this->assertSame('HOME-20', $fake->rows('/ppp/secret')[0]['profile']);
        $this->assertSame(CustomerStatus::Active, $customer->fresh()->status);
        $this->assertNull($customer->fresh()->isolated_at);
    }
}
