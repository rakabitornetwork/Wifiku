<?php

namespace App\Jobs\GenieAcs;

use App\Models\Asset;
use App\Services\GenieAcs\GenieAcsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Menarik redaman optik (RX Power) dan waktu inform terakhir ONT dari GenieACS.
 */
class FetchOntRxPowerJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [60];

    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public Asset $asset) {}

    public function uniqueId(): string
    {
        return (string) $this->asset->id;
    }

    /**
     * Execute the job.
     */
    public function handle(GenieAcsService $genieAcs): void
    {
        $status = $genieAcs->readStatus($this->asset);

        $this->asset->forceFill([
            'last_rx_power' => $status['rx_power'],
            'last_seen_at' => $status['last_inform'] ?? $this->asset->last_seen_at,
        ])->save();
    }
}
