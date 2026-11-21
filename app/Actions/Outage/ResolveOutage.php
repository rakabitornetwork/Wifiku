<?php

namespace App\Actions\Outage;

use App\Enums\OutageStatus;
use App\Jobs\Notification\BroadcastOutageJob;
use App\Models\Outage;

class ResolveOutage
{
    /**
     * Tutup gangguan; pengumuman "sudah normal" dikirim hanya bila diminta.
     */
    public function handle(Outage $outage, bool $notifyCustomers): Outage
    {
        if ($outage->status === OutageStatus::Resolved) {
            return $outage;
        }

        $outage->update(['status' => OutageStatus::Resolved, 'resolved_at' => now()]);

        if ($notifyCustomers) {
            BroadcastOutageJob::dispatch($outage, resolved: true);
        }

        return $outage;
    }
}
