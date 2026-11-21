<?php

namespace Tests\Feature\Jobs\Network;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Jobs\Network\IsolateCustomerJob;
use App\Jobs\Network\ReopenCustomerJob;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class IsolationJobsTest extends TestCase
{
    use FakesRouterOs;
    use RefreshDatabase;

    public function test_isolate_job_isolates_customer_still_overdue(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create(['due_date' => now()->subDay(), 'status' => InvoiceStatus::Unpaid]);

        IsolateCustomerJob::dispatch($customer);

        $this->assertSame(CustomerStatus::Isolated, $customer->fresh()->status);
    }

    public function test_isolate_job_skips_customer_who_paid_before_it_ran(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->for($customer)->create(['due_date' => now()->subDay(), 'status' => InvoiceStatus::Unpaid]);
        $job = new IsolateCustomerJob($customer);
        $invoice->update(['status' => InvoiceStatus::Paid]);

        dispatch($job);

        $this->assertSame(CustomerStatus::Active, $customer->fresh()->status);
        $this->assertSame([], $this->fakeRouterOs($customer->router)->commands);
    }

    public function test_reopen_job_reopens_paid_up_isolated_customer(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Isolated]);

        ReopenCustomerJob::dispatch($customer);

        $this->assertSame(CustomerStatus::Active, $customer->fresh()->status);
    }

    public function test_reopen_job_leaves_customer_isolated_while_overdue_without_promise(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Isolated]);
        Invoice::factory()->for($customer)->create(['due_date' => now()->subDay(), 'status' => InvoiceStatus::Unpaid]);

        ReopenCustomerJob::dispatch($customer);

        $this->assertSame(CustomerStatus::Isolated, $customer->fresh()->status);
    }
}
