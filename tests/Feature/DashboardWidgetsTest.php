<?php

namespace Tests\Feature;

use App\Actions\Router\CheckRouterConnection;
use App\Enums\AssetStatus;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RouterStatus;
use App\Enums\VoucherStatus;
use App\Enums\WhatsappMessageStatus;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\HotspotProfile;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Models\WhatsappMessage;
use App\Services\Mikrotik\MikrotikConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    public function test_owner_gets_every_widget_with_safe_empty_defaults(): void
    {
        $this->actingAs($this->owner())->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('widgets.finance.trend', 6)
            ->where('widgets.finance.revenue_change', null)
            ->where('widgets.collection.percent', 0)
            ->where('widgets.overdue.count', 0)
            ->where('widgets.weak_signal.count', 0)
            ->has('widgets.attention', 5)
            ->has('widgets.activity'));
    }

    public function test_technicians_do_not_receive_financial_or_audit_widgets(): void
    {
        Payment::factory()->create(['status' => PaymentStatus::Paid, 'paid_at' => now(), 'amount' => 777777]);

        $response = $this->actingAs($this->userWithRole('Teknisi'))->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('widgets.finance')
            ->missing('widgets.collection')
            ->missing('widgets.overdue')
            ->missing('widgets.activity'));

        $this->assertStringNotContainsString('777777', $response->getContent());
    }

    public function test_finance_widget_sums_received_payments_and_expenses_and_compares_with_last_month(): void
    {
        Payment::factory()->create(['status' => PaymentStatus::Paid, 'paid_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3), 'amount' => 100000]);
        Payment::factory()->create(['status' => PaymentStatus::Paid, 'paid_at' => now()->startOfMonth()->addHour(), 'amount' => 150000]);
        Payment::factory()->create(['status' => PaymentStatus::Pending, 'amount' => 999999]);
        Expense::factory()->create(['expense_date' => now()->toDateString(), 'amount' => 40000]);

        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('widgets.finance.current.revenue', 150000)
            ->where('widgets.finance.current.expenses', 40000)
            ->where('widgets.finance.current.net_profit', 110000)
            ->where('widgets.finance.revenue_change', 50));
    }

    public function test_collection_widget_counts_only_this_months_non_cancelled_customer_invoices(): void
    {
        Invoice::factory()->create(['total' => 100000, 'amount_paid' => 100000, 'status' => InvoiceStatus::Paid, 'issue_date' => now()->startOfMonth()]);
        Invoice::factory()->create(['total' => 100000, 'amount_paid' => 0, 'status' => InvoiceStatus::Unpaid, 'issue_date' => now()->startOfMonth()]);
        Invoice::factory()->create(['total' => 500000, 'status' => InvoiceStatus::Cancelled, 'issue_date' => now()->startOfMonth()]);
        Invoice::factory()->create(['total' => 300000, 'amount_paid' => 300000, 'status' => InvoiceStatus::Paid, 'issue_date' => now()->subMonths(2)]);

        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('widgets.collection.invoices', 2)
            ->where('widgets.collection.paid_invoices', 1)
            ->where('widgets.collection.billed', 200000)
            ->where('widgets.collection.collected', 100000)
            ->where('widgets.collection.percent', 50));
    }

    public function test_overdue_widget_lists_the_longest_overdue_first_and_skips_paid_or_not_yet_due(): void
    {
        $late = Invoice::factory()->create(['total' => 120000, 'amount_paid' => 20000, 'status' => InvoiceStatus::Partial, 'due_date' => now()->subDays(10), 'period_start' => now()->subMonths(3)->startOfMonth()]);
        Invoice::factory()->create(['total' => 90000, 'status' => InvoiceStatus::Unpaid, 'due_date' => now()->subDays(3), 'period_start' => now()->subMonths(2)->startOfMonth()]);
        Invoice::factory()->create(['status' => InvoiceStatus::Unpaid, 'due_date' => now()->addDays(5), 'period_start' => now()->subMonth()->startOfMonth()]);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'due_date' => now()->subDays(20), 'period_start' => now()->startOfMonth()]);

        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('widgets.overdue.count', 2)
            ->where('widgets.overdue.total', 190000)
            ->where('widgets.overdue.items.0.invoice_number', $late->invoice_number)
            ->where('widgets.overdue.items.0.outstanding', 100000)
            ->where('widgets.overdue.items.0.days_late', 10)
            ->has('widgets.overdue.items', 2));
    }

    public function test_weak_signal_widget_orders_worst_first_and_flags_critical(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi']);
        Asset::factory()->create(['customer_id' => $customer->id, 'status' => AssetStatus::Installed, 'last_rx_power' => -28.4]);
        Asset::factory()->create(['status' => AssetStatus::Installed, 'last_rx_power' => -25.8]);
        Asset::factory()->create(['status' => AssetStatus::Installed, 'last_rx_power' => -19.0]);
        Asset::factory()->create(['status' => AssetStatus::InStock, 'last_rx_power' => -40.0]);

        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('widgets.weak_signal.count', 2)
            ->where('widgets.weak_signal.critical', 1)
            ->where('widgets.weak_signal.items.0.customer', 'Budi')
            ->where('widgets.weak_signal.items.0.level', 'critical')
            ->where('widgets.weak_signal.items.1.level', 'warning'));
    }

    public function test_attention_widget_counts_open_outages_expired_promises_new_installs_and_failed_messages(): void
    {
        Outage::factory()->create(['title' => 'FO putus Desa A']);
        Outage::factory()->create(['status' => 'resolved']);
        Customer::factory()->create(['status' => CustomerStatus::Active, 'promise_to_pay_until' => now()->subDay(), 'installed_at' => now()->subYear()]);
        Customer::factory()->create(['status' => CustomerStatus::Active, 'promise_to_pay_until' => now()->addDays(3), 'installed_at' => now()->subYear()]);
        Customer::factory()->create(['installed_at' => now()->startOfMonth()->addDay()]);
        WhatsappMessage::factory()->create(['status' => WhatsappMessageStatus::Failed]);
        WhatsappMessage::factory()->create(['status' => WhatsappMessageStatus::Sent]);

        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('widgets.attention.0.value', 1)
            ->where('widgets.attention.0.hint', 'FO putus Desa A')
            ->where('widgets.attention.1.value', 1)
            ->where('widgets.attention.2.value', 1)
            ->where('widgets.attention.3.value', 1));
    }

    public function test_upcoming_widget_lists_invoices_due_today_through_three_days_ahead_only(): void
    {
        $today = Invoice::factory()->create(['total' => 80000, 'status' => InvoiceStatus::Unpaid, 'due_date' => now(), 'period_start' => now()->subMonths(3)->startOfMonth()]);
        Invoice::factory()->create(['total' => 90000, 'status' => InvoiceStatus::Partial, 'amount_paid' => 40000, 'due_date' => now()->addDays(3), 'period_start' => now()->subMonths(2)->startOfMonth()]);
        Invoice::factory()->create(['status' => InvoiceStatus::Unpaid, 'due_date' => now()->addDays(4), 'period_start' => now()->subMonth()->startOfMonth()]);
        Invoice::factory()->create(['status' => InvoiceStatus::Unpaid, 'due_date' => now()->subDay(), 'period_start' => now()->startOfMonth()]);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'due_date' => now()->addDay(), 'period_start' => now()->subMonths(4)->startOfMonth()]);

        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('widgets.upcoming.count', 2)
            ->where('widgets.upcoming.total', 130000)
            ->where('widgets.upcoming.items.0.invoice_number', $today->invoice_number)
            ->where('widgets.upcoming.items.0.due_in_days', 0)
            ->where('widgets.upcoming.items.1.due_in_days', 3));
    }

    public function test_offline_routers_are_listed_longest_outage_first_with_unknown_start_last(): void
    {
        Router::factory()->create(['name' => 'Online', 'status' => RouterStatus::Online]);
        Router::factory()->create(['name' => 'Baru putus', 'status' => RouterStatus::Offline, 'offline_since' => now()->subMinutes(20)]);
        Router::factory()->create(['name' => 'Lama putus', 'status' => RouterStatus::Offline, 'offline_since' => now()->subHours(5)]);
        Router::factory()->create(['name' => 'Belum diketahui', 'status' => RouterStatus::Offline, 'offline_since' => null]);

        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('widgets.routers_offline.offline', 3)
            ->where('widgets.routers_offline.total', 4)
            ->where('widgets.routers_offline.items.0.name', 'Lama putus')
            ->where('widgets.routers_offline.items.1.name', 'Baru putus')
            ->where('widgets.routers_offline.items.2.name', 'Belum diketahui')
            ->where('widgets.routers_offline.items.2.offline_since', null));
    }

    public function test_router_check_remembers_when_the_outage_started_and_clears_it_on_recovery(): void
    {
        $router = Router::factory()->create(['status' => RouterStatus::Online]);
        $fake = $this->fakeRouterOs($router);

        $this->travelTo(now()->setTime(10, 0));
        $fake->failWith = new MikrotikConnectionException('timeout');
        app(CheckRouterConnection::class)->handle($router);
        $started = $router->fresh()->offline_since;
        $this->assertNotNull($started);

        $this->travel(15)->minutes();
        app(CheckRouterConnection::class)->handle($router->fresh());
        $this->assertTrue($router->fresh()->offline_since->equalTo($started));

        $fake->failWith = null;
        app(CheckRouterConnection::class)->handle($router->fresh());
        $this->assertNull($router->fresh()->offline_since);
        $this->assertSame(RouterStatus::Online, $router->fresh()->status);
    }

    public function test_voucher_today_widget_counts_todays_first_logins_against_yesterday(): void
    {
        $profile = HotspotProfile::factory()->create(['name' => '3 Jam']);
        $batch = VoucherBatch::factory()->create(['router_id' => $profile->router_id, 'hotspot_profile_id' => $profile->id]);
        $make = fn (array $attributes) => Voucher::factory()->create([...$attributes, 'voucher_batch_id' => $batch->id, 'router_id' => $profile->router_id, 'hotspot_profile_id' => $profile->id]);

        $make(['status' => VoucherStatus::Active, 'price' => 5000, 'first_login_at' => now()]);
        $make(['status' => VoucherStatus::Expired, 'price' => 5000, 'first_login_at' => now()]);
        $make(['status' => VoucherStatus::Unused, 'price' => 5000, 'first_login_at' => null]);
        $make(['status' => VoucherStatus::Active, 'price' => 5000, 'first_login_at' => now()->subDay()]);

        $this->actingAs($this->owner())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('widgets.voucher_today.count', 2)
            ->where('widgets.voucher_today.revenue', 10000)
            ->where('widgets.voucher_today.yesterday_revenue', 5000)
            ->where('widgets.voucher_today.revenue_change', 100)
            ->where('widgets.voucher_today.top_profiles.0.name', '3 Jam'));
    }
}
