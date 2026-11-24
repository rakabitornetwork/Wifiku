<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\OutageStatus;
use App\Http\Requests\UpdateCableRouteRequest;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Router;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TopologyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Topology/Index', [
            'odps' => Odp::query()
                ->with('router:id,name')
                ->withCount(['customers', 'outages as open_outages_count' => fn ($query) => $query->where('status', OutageStatus::Open)])
                ->orderBy('name')
                ->get(),
            // Hanya kolom minimum untuk pin peta; pelanggan tanpa koordinat tidak ikut.
            'customers' => Customer::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('status', '!=', CustomerStatus::Inactive)
                ->get(['id', 'customer_code', 'name', 'latitude', 'longitude', 'status', 'odp_id', 'cable_path']),
            'routers' => Router::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Simpan titik belok kabel agar garis ODP → pelanggan mengikuti jalan.
     */
    public function updateCableRoute(UpdateCableRouteRequest $request, Customer $customer): RedirectResponse
    {
        $path = collect($request->validated('path') ?? [])
            ->map(fn (array $point): array => [round((float) $point[0], 7), round((float) $point[1], 7)])
            ->values()
            ->all();

        $customer->update(['cable_path' => $path === [] ? null : $path]);

        return back();
    }
}
