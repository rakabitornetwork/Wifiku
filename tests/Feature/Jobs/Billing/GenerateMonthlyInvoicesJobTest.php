<?php

namespace Tests\Feature\Jobs\Billing;

use App\Enums\CustomerStatus;
use App\Jobs\Billing\GenerateCustomerInvoiceJob;
use App\Jobs\Billing\GenerateMonthlyInvoicesJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateMonthlyInvoicesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_only_for_billable_customers_without_a_current_invoice(): void
    {
        $this->travelTo('2026-11-05');
        Queue::fake([GenerateCustomerInvoiceJob::class]);

        $due = Customer::factory()->create(['installed_at' => '2026-01-01']);
        $isolated = Customer::factory()->create(['installed_at' => '2026-01-01', 'status' => CustomerStatus::Isolated]);
        $alreadyBilled = Customer::factory()->create(['installed_at' => '2026-01-01']);
        Invoice::factory()->for($alreadyBilled)->create(['period_start' => '2026-11-01']);
        Customer::factory()->create(['installed_at' => '2026-01-01', 'status' => CustomerStatus::Inactive]);
        Customer::factory()->create(['installed_at' => '2026-11-20']);

        (new GenerateMonthlyInvoicesJob)->handle();

        Queue::assertPushed(GenerateCustomerInvoiceJob::class, 2);
        Queue::assertPushed(GenerateCustomerInvoiceJob::class, fn ($job) => $job->customer->is($due) && $job->today->toDateString() === '2026-11-05');
        Queue::assertPushed(GenerateCustomerInvoiceJob::class, fn ($job) => $job->customer->is($isolated));
    }

    public function test_customers_on_a_free_package_are_not_billed(): void
    {
        $this->travelTo('2026-11-05');
        Queue::fake([GenerateCustomerInvoiceJob::class]);

        $paying = Customer::factory()->create(['installed_at' => '2026-01-01']);
        Customer::factory()->for(Package::factory()->create(['price' => 0]))->create(['installed_at' => '2026-01-01']);

        (new GenerateMonthlyInvoicesJob)->handle();

        Queue::assertPushed(GenerateCustomerInvoiceJob::class, 1);
        Queue::assertPushed(GenerateCustomerInvoiceJob::class, fn ($job) => $job->customer->is($paying));
    }

    public function test_end_to_end_run_creates_one_invoice_per_customer_even_when_repeated(): void
    {
        $this->travelTo('2026-11-01 00:10');
        Customer::factory()->count(3)->create(['installed_at' => '2026-01-01', 'billing_day' => 1]);

        (new GenerateMonthlyInvoicesJob)->handle();
        (new GenerateMonthlyInvoicesJob)->handle();

        $this->assertSame(3, Invoice::count());
    }
}
