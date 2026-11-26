<?php

namespace App\Actions\Outage;

use App\Enums\CustomerStatus;
use App\Enums\OutageStatus;
use App\Jobs\Notification\BroadcastOutageJob;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Outage;
use App\Models\User;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BroadcastOutage
{
    public function __construct(private readonly TelegramNotifier $telegram) {}

    /**
     * Catat gangguan pada satu ODP lalu antrekan pengumuman WhatsApp ke seluruh pelanggan di ODP itu.
     * Satu ODP hanya boleh punya satu gangguan terbuka agar pelanggan tidak menerima pengumuman ganda.
     */
    public function handle(Odp $odp, string $title, string $message, User $creator): Outage
    {
        if ($odp->outages()->where('status', OutageStatus::Open)->exists()) {
            throw ValidationException::withMessages(['odp_id' => 'ODP ini masih memiliki gangguan yang belum diselesaikan.']);
        }

        $outage = $odp->outages()->create([
            'title' => $title,
            'message' => $message,
            'status' => OutageStatus::Open,
            'created_by' => $creator->id,
        ]);

        BroadcastOutageJob::dispatch($outage, resolved: false);
        $this->telegram->outageOpened($outage);

        return $outage;
    }

    /**
     * Pelanggan di ODP yang layanannya masih berjalan (bukan nonaktif).
     *
     * @return Builder<Customer>
     */
    public static function recipients(Odp $odp): Builder
    {
        return $odp->customers()->getQuery()->where('status', '!=', CustomerStatus::Inactive);
    }
}
