<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\OutageStatus;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Router;
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
                ->get(['id', 'customer_code', 'name', 'latitude', 'longitude', 'status', 'odp_id']),
            'routers' => Router::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
