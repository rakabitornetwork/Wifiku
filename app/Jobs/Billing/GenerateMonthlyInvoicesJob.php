<?php

namespace App\Jobs\Billing;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Dijalankan harian: men-dispatch GenerateCustomerInvoiceJob untuk setiap pelanggan yang siklus berjalannya belum ditagih.
 */
class GenerateMonthlyInvoicesJob implements ShouldQueue
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
            ->whereIn('status', [CustomerStatus::Active, CustomerStatus::Isolated])
            ->where('installed_at', '<=', $today)
            // Invoice siklus berjalan selalu berakhir pada/ setelah hari ini; bila sudah ada, pelanggan dilewati.
            ->whereDoesntHave('invoices', fn ($query) => $query->where('period_end', '>=', $today))
            ->chunkById(200, function (Collection $customers) use ($today): void {
                foreach ($customers as $customer) {
                    GenerateCustomerInvoiceJob::dispatch($customer, $today);
                }
            });
    }
}
