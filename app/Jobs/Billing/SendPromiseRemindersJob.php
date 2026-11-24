<?php

namespace App\Jobs\Billing;

use App\Actions\Customer\GrantPaymentExtension;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Services\Whatsapp\CustomerNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Mengingatkan pelanggan H-1 sebelum masa perpanjangan pembayaran (Janji Bayar) berakhir, bila tunggakan masih ada.
 */
class SendPromiseRemindersJob implements ShouldQueue
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
    public function handle(GrantPaymentExtension $extensions, CustomerNotifier $notifier): void
    {
        Customer::query()
            ->where('status', CustomerStatus::Active)
            ->whereDate('promise_to_pay_until', CarbonImmutable::today()->addDay()->toDateString())
            ->chunkById(200, function (Collection $customers) use ($extensions, $notifier): void {
                foreach ($customers as $customer) {
                    $outstanding = $extensions->outstanding($customer);

                    if ($outstanding['amount'] > 0) {
                        $notifier->extensionEnding($customer, $outstanding['amount']);
                    }
                }
            });
    }
}
