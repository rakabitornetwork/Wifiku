<?php

namespace Tests\Feature;

use App\Actions\Customer\GrantPaymentExtension;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Jobs\Billing\SendPromiseRemindersJob;
use App\Jobs\Network\ReopenCustomerJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentExtension;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PaymentExtensionTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10');
        Queue::fake();
    }

    public function test_admin_can_grant_an_extension_within_the_short_limit(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin())
            ->post("/customers/{$customer->id}/extensions", ['until' => '2026-11-24', 'reason' => 'Menunggu gaji tanggal 5'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-11-24', $customer->fresh()->promise_to_pay_until->toDateString());
        $this->assertDatabaseHas('payment_extensions', ['customer_id' => $customer->id, 'days' => 14, 'reason' => 'Menunggu gaji tanggal 5']);
    }

    public function test_admin_cannot_exceed_the_short_limit_but_owner_can_up_to_two_months(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin())
            ->post("/customers/{$customer->id}/extensions", ['until' => '2027-01-08', 'reason' => 'Minta dua bulan'])
            ->assertSessionHasErrors('until');
        $this->assertNull($customer->fresh()->promise_to_pay_until);

        $this->actingAs($this->owner())
            ->post("/customers/{$customer->id}/extensions", ['until' => '2027-01-08', 'reason' => 'Disetujui Owner'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2027-01-08', $customer->fresh()->promise_to_pay_until->toDateString());

        $this->actingAs($this->owner())
            ->post("/customers/{$customer->id}/extensions", ['until' => '2027-03-01', 'reason' => 'Terlalu lama'])
            ->assertSessionHasErrors('until');
    }

    public function test_date_must_be_in_the_future(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->owner())
            ->post("/customers/{$customer->id}/extensions", ['until' => '2026-11-10', 'reason' => 'Hari ini tidak cukup'])
            ->assertSessionHasErrors('until');
    }

    public function test_reason_is_required(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->owner())
            ->post("/customers/{$customer->id}/extensions", ['until' => '2026-11-20', 'reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertNull($customer->fresh()->promise_to_pay_until);
    }

    public function test_inactive_customer_cannot_be_granted_an_extension(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Inactive]);

        $this->actingAs($this->owner())
            ->post("/customers/{$customer->id}/extensions", ['until' => '2026-11-20', 'reason' => 'Tes alasan'])
            ->assertSessionHasErrors('until');
    }

    public function test_extension_reopens_an_isolated_customer(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Isolated]);

        $this->actingAs($this->admin())->post("/customers/{$customer->id}/extensions", ['until' => '2026-11-20', 'reason' => 'Janji bayar minggu depan']);

        Queue::assertPushed(ReopenCustomerJob::class, fn ($job) => $job->customer->is($customer));
    }

    public function test_extension_history_records_previous_date_and_grantor(): void
    {
        $customer = Customer::factory()->create(['promise_to_pay_until' => '2026-11-12']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/customers/{$customer->id}/extensions", ['until' => '2026-11-20', 'reason' => 'Perpanjang lagi']);

        $this->actingAs($admin)->getJson("/customers/{$customer->id}/extensions")
            ->assertOk()
            ->assertJsonPath('max_days', 14)
            ->assertJsonPath('history.0.previous_until', '2026-11-12')
            ->assertJsonPath('history.0.granted_by', $admin->name);
    }

    public function test_outstanding_covers_all_unpaid_invoices(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create(['total' => 100000, 'amount_paid' => 0, 'status' => InvoiceStatus::Unpaid, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31']);
        Invoice::factory()->for($customer)->create(['total' => 100000, 'amount_paid' => 40000, 'status' => InvoiceStatus::Partial, 'period_start' => '2026-11-01', 'period_end' => '2026-11-30']);

        $this->assertSame(['count' => 2, 'amount' => 160000], $this->app->make(GrantPaymentExtension::class)->outstanding($customer));
    }

    public function test_technician_cannot_grant_extensions(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole('Teknisi'))
            ->post("/customers/{$customer->id}/extensions", ['until' => '2026-11-20', 'reason' => 'Tidak berwenang'])
            ->assertForbidden();
    }

    public function test_reminder_is_sent_the_day_before_the_extension_ends_only_with_outstanding_balance(): void
    {
        $withDebt = Customer::factory()->create(['promise_to_pay_until' => '2026-11-11', 'whatsapp' => '6281111111111']);
        Invoice::factory()->for($withDebt)->create(['total' => 150000, 'amount_paid' => 0, 'status' => InvoiceStatus::Unpaid]);
        Customer::factory()->create(['promise_to_pay_until' => '2026-11-11', 'whatsapp' => '6282222222222']);
        Customer::factory()->create(['promise_to_pay_until' => '2026-11-15', 'whatsapp' => '6283333333333']);

        $this->app->make(SendPromiseRemindersJob::class)->handle($this->app->make(GrantPaymentExtension::class), $this->app->make(CustomerNotifier::class));

        $this->assertSame(1, WhatsappMessage::count());
        $this->assertSame($withDebt->id, WhatsappMessage::first()->customer_id);
    }

    public function test_customer_form_no_longer_accepts_a_promise_date(): void
    {
        $customer = Customer::factory()->create();
        $this->assertSame(0, PaymentExtension::count());

        $this->actingAs($this->owner())
            ->put("/customers/{$customer->id}", [...$customer->only(['router_id', 'package_id', 'name', 'whatsapp', 'pppoe_username', 'billing_cycle', 'billing_day', 'due_days']), 'status' => 'active', 'promise_to_pay_until' => '2027-12-31']);

        $this->assertNull($customer->fresh()->promise_to_pay_until);
    }
}
