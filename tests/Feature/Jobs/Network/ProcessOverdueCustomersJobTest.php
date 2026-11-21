<?php

namespace Tests\Feature\Jobs\Network;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Jobs\Network\IsolateCustomerJob;
use App\Jobs\Network\ProcessOverdueCustomersJob;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessOverdueCustomersJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_isolation_for_overdue_customers_outside_promise_period(): void
    {
        $this->travelTo('2026-11-15');
        Queue::fake([IsolateCustomerJob::class]);

        $overdue = Customer::factory()->create();
        Invoice::factory()->for($overdue)->create(['due_date' => '2026-11-14', 'status' => InvoiceStatus::Unpaid]);

        $expiredPromise = Customer::factory()->create(['promise_to_pay_until' => '2026-11-14']);
        Invoice::factory()->for($expiredPromise)->create(['due_date' => '2026-11-01', 'status' => InvoiceStatus::Partial]);

        $underPromise = Customer::factory()->create(['promise_to_pay_until' => '2026-11-15']);
        Invoice::factory()->for($underPromise)->create(['due_date' => '2026-11-01', 'status' => InvoiceStatus::Unpaid]);

        $notYetDue = Customer::factory()->create();
        Invoice::factory()->for($notYetDue)->create(['due_date' => '2026-11-15', 'status' => InvoiceStatus::Unpaid]);

        $alreadyIsolated = Customer::factory()->create(['status' => CustomerStatus::Isolated]);
        Invoice::factory()->for($alreadyIsolated)->create(['due_date' => '2026-11-01', 'status' => InvoiceStatus::Unpaid]);

        (new ProcessOverdueCustomersJob)->handle();

        Queue::assertPushed(IsolateCustomerJob::class, 2);
        Queue::assertPushed(IsolateCustomerJob::class, fn ($job) => $job->customer->is($overdue));
        Queue::assertPushed(IsolateCustomerJob::class, fn ($job) => $job->customer->is($expiredPromise));
    }
}
