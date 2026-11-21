<?php

namespace App\Jobs\Network;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Mencari pelanggan yang melewati jatuh tempo (di luar masa Janji Bayar) lalu men-dispatch IsolateCustomerJob.
 */
class ProcessOverdueCustomersJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $today = CarbonImmutable::today();

        Customer::query()
            ->where('status', CustomerStatus::Active)
            ->where(fn ($query) => $query->whereNull('promise_to_pay_until')->orWhere('promise_to_pay_until', '<', $today))
            ->whereHas('invoices', fn ($query) => $query
                ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
                ->where('due_date', '<', $today))
            ->chunkById(200, function (Collection $customers): void {
                foreach ($customers as $customer) {
                    IsolateCustomerJob::dispatch($customer);
                }
            });
    }
}
