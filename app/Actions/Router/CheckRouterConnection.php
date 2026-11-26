<?php

namespace App\Actions\Router;

use App\Enums\RouterStatus;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Support\Str;

class CheckRouterConnection
{
    public function __construct(private readonly TelegramNotifier $telegram) {}

    /**
     * Uji login API ke router lalu simpan status Online/Offline beserta pesan galatnya.
     */
    public function handle(Router $router): bool
    {
        try {
            MikrotikService::forRouter($router)->identity();
            $online = true;
            $error = null;
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            $online = false;
            $error = Str::limit($exception->getMessage(), 250);
        }

        $wasOffline = $router->status === RouterStatus::Offline;
        $previousSince = $router->offline_since;

        // Awal masa offline dicatat sekali dan dipertahankan selama router tetap offline; dikosongkan saat kembali online.
        $offlineSince = match (true) {
            $online => null,
            $router->status === RouterStatus::Offline => $router->offline_since ?? now(),
            default => now(),
        };

        $router->forceFill([
            'status' => $online ? RouterStatus::Online : RouterStatus::Offline,
            'last_checked_at' => now(),
            'offline_since' => $offlineSince,
            'last_error' => $error,
        ])->save();

        // Hanya perubahan keadaan yang diberitahukan, bukan tiap pengecekan 5 menit.
        if (! $online && ! $wasOffline) {
            $this->telegram->routerOffline($router);
        } elseif ($online && $wasOffline) {
            $this->telegram->routerRecovered($router, $previousSince?->diffInMinutes(now()) !== null ? (int) $previousSince->diffInMinutes(now()) : null);
        }

        return $online;
    }
}
