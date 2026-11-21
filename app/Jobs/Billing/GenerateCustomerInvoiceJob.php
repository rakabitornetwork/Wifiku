<?php

namespace App\Jobs\Billing;

use App\Models\Customer;
use App\Services\Billing\InvoiceGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Membuat invoice satu pelanggan untuk siklus yang memuat $today (termasuk prorata bulan pertama).
 */
class GenerateCustomerInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public Customer $customer, public CarbonImmutable $today) {}

    public function uniqueId(): string
    {
        return $this->customer->id.':'.$this->today->toDateString();
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceGeneratorService $generator): void
    {
        $generator->generateFor($this->customer, $this->today);
    }
}
