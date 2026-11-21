<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Services\GenieAcs\GenieAcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitoring ONT terpasang (redaman optik & status TR-069 via GenieACS).
 */
class MonitoringController extends Controller
{
    private const WARNING_DBM = -25;

    private const CRITICAL_DBM = -27;

    public function index(Request $request, GenieAcsService $genieAcs): Response
    {
        $search = trim((string) $request->query('search'));
        $signal = in_array($request->query('signal'), ['good', 'warning', 'critical', 'unknown'], true) ? $request->query('signal') : '';

        $installedOnts = fn () => Asset::query()->where('type', AssetType::Ont)->where('status', AssetStatus::Installed);

        $onts = $installedOnts()
            ->with('customer:id,customer_code,name')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('customer_code', 'like', "%{$search}%"));
            }))
            ->when($signal === 'good', fn ($query) => $query->where('last_rx_power', '>=', self::WARNING_DBM))
            ->when($signal === 'warning', fn ($query) => $query->where('last_rx_power', '<', self::WARNING_DBM)->where('last_rx_power', '>=', self::CRITICAL_DBM))
            ->when($signal === 'critical', fn ($query) => $query->where('last_rx_power', '<', self::CRITICAL_DBM))
            ->when($signal === 'unknown', fn ($query) => $query->whereNull('last_rx_power'))
            ->orderByRaw('last_rx_power is null')
            ->orderBy('last_rx_power')
            ->paginate($this->perPage(15))
            ->withQueryString();

        return Inertia::render('Monitoring/Index', [
            'onts' => $onts,
            'filters' => ['search' => $search, 'signal' => $signal],
            'summary' => [
                'total' => $installedOnts()->count(),
                'good' => $installedOnts()->where('last_rx_power', '>=', self::WARNING_DBM)->count(),
                'warning' => $installedOnts()->where('last_rx_power', '<', self::WARNING_DBM)->where('last_rx_power', '>=', self::CRITICAL_DBM)->count(),
                'critical' => $installedOnts()->where('last_rx_power', '<', self::CRITICAL_DBM)->count(),
                'unknown' => $installedOnts()->whereNull('last_rx_power')->count(),
            ],
            'genieacsConfigured' => $genieAcs->isConfigured(),
        ]);
    }
}
