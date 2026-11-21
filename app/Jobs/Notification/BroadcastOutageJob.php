<?php

namespace App\Jobs\Notification;

use App\Actions\Outage\BroadcastOutage;
use App\Models\Customer;
use App\Models\Outage;
use App\Services\Whatsapp\CustomerNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Mengirim pengumuman gangguan (atau pemulihan) ke seluruh pelanggan satu ODP.
 * Setiap pesan masuk outbox lalu dikirim SendWhatsappMessageJob yang dibatasi rate limiter.
 */
class BroadcastOutageJob implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(public Outage $outage, public bool $resolved = false) {}

    /**
     * Execute the job.
     */
    public function handle(CustomerNotifier $notifier): void
    {
        $outage = $this->outage->loadMissing('odp');
        $count = 0;

        BroadcastOutage::recipients($outage->odp)->chunkById(200, function (Collection $customers) use ($notifier, $outage, &$count): void {
            /** @var Customer $customer */
            foreach ($customers as $customer) {
                $this->resolved ? $notifier->outageResolved($customer, $outage) : $notifier->outage($customer, $outage);
                $count++;
            }
        });

        if (! $this->resolved) {
            $outage->update(['notified_count' => $count]);
        }
    }
}
