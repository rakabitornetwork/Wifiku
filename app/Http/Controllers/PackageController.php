<?php

namespace App\Http\Controllers;

use App\Http\Requests\PackageRequest;
use App\Jobs\Network\RemovePppProfileJob;
use App\Jobs\Network\SyncPppProfileJob;
use App\Models\Package;
use App\Models\Router;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $routerId = $request->integer('router_id') ?: null;

        $packages = Package::query()
            ->with('router:id,name')
            ->withCount('customers')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($routerId, fn ($query) => $query->where('router_id', $routerId))
            ->orderBy('price')
            ->orderBy('name')
            ->paginate($this->perPage(15))
            ->withQueryString();

        return Inertia::render('Packages/Index', [
            'packages' => $packages,
            'routers' => Router::orderBy('name')->get(['id', 'name']),
            'search' => $search,
            'routerId' => $routerId ? (string) $routerId : '',
        ]);
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        $package = Package::create($request->validated());

        foreach ($this->routerIds($package->router_id) as $routerId) {
            SyncPppProfileJob::dispatch($package, $routerId);
        }

        Toast::success('Paket berhasil ditambahkan; profile PPP-nya di router dibuat lewat antrean.');

        return back();
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $previousName = $package->mikrotik_profile;
        $previousRouterIds = $this->routerIds($package->router_id);

        $package->update($request->validated());

        $routerIds = $this->routerIds($package->router_id);

        foreach (array_diff($previousRouterIds, $routerIds) as $routerId) {
            RemovePppProfileJob::dispatch($routerId, $previousName);
        }

        foreach ($routerIds as $routerId) {
            SyncPppProfileJob::dispatch($package, $routerId, $previousName);
        }

        Toast::success('Paket berhasil diperbarui; profile PPP di router ikut diperbarui lewat antrean.');

        return back();
    }

    public function destroy(Package $package): RedirectResponse
    {
        if ($package->customers()->exists()) {
            Toast::error('Paket masih dipakai pelanggan. Nonaktifkan paket jika tidak ingin dijual lagi.');

            return back();
        }

        $package->delete();

        foreach ($this->routerIds($package->router_id) as $routerId) {
            RemovePppProfileJob::dispatch($routerId, $package->mikrotik_profile);
        }

        Toast::success('Paket dihapus; profile PPP di router ikut dihapus lewat antrean bila tidak sedang dipakai.');

        return back();
    }

    /**
     * Router yang menjadi cakupan paket: router tertentu, atau semua router bila paket berlaku untuk semua.
     *
     * @return list<int>
     */
    private function routerIds(?int $routerId): array
    {
        return $routerId !== null ? [$routerId] : Router::pluck('id')->all();
    }
}
