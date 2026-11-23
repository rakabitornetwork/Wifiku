<?php

namespace App\Http\Controllers;

use App\Actions\Router\ImportHotspotProfiles;
use App\Http\Requests\HotspotProfileRequest;
use App\Jobs\Network\RemoveHotspotProfileJob;
use App\Jobs\Network\SyncHotspotProfileJob;
use App\Models\HotspotProfile;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Mikrotik\MikrotikService;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotspotProfileController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Hotspot/Profiles', [
            'profiles' => HotspotProfile::query()
                ->with('router:id,name')
                ->withCount('vouchers')
                ->orderBy('router_id')
                ->orderBy('price')
                ->paginate($this->perPage(15))
                ->withQueryString(),
            'routers' => Router::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Baca Hotspot User Profile dari router untuk ditinjau sebelum impor.
     */
    public function importPreview(Router $router, ImportHotspotProfiles $import): JsonResponse
    {
        try {
            return response()->json(['profiles' => $import->preview($router)]);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            return response()->json(['error' => "Gagal membaca router: {$exception->getMessage()}"], 422);
        }
    }

    /**
     * Daftar Hotspot Server pada router, untuk pilihan server saat generate voucher.
     */
    public function servers(Router $router): JsonResponse
    {
        try {
            return response()->json(['servers' => MikrotikService::forRouter($router)->hotspotServers()]);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            return response()->json(['error' => "Gagal membaca router: {$exception->getMessage()}"], 422);
        }
    }

    public function import(Request $request, Router $router, ImportHotspotProfiles $import): RedirectResponse
    {
        $validated = $request->validate([
            'names' => ['required', 'array', 'min:1', 'max:200'],
            'names.*' => ['string', 'max:255'],
        ]);

        try {
            $report = $import->handle($router, $validated['names']);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            Toast::error("Gagal membaca router: {$exception->getMessage()}");

            return back();
        }

        Toast::success("{$report['imported']} profil hotspot diimpor dari {$router->name}".($report['existing'] > 0 ? ", {$report['existing']} sudah ada dan dilewati" : '').'. Periksa harga & masa aktifnya sebelum dijual.');

        return back();
    }

    public function store(HotspotProfileRequest $request): RedirectResponse
    {
        $profile = HotspotProfile::create($request->validated());
        SyncHotspotProfileJob::dispatch($profile);

        Toast::success('Profil hotspot berhasil ditambahkan; pembuatannya di router berjalan lewat antrean.');

        return back();
    }

    public function update(HotspotProfileRequest $request, HotspotProfile $profile): RedirectResponse
    {
        $previousName = $profile->mikrotik_profile;
        $previousRouterId = $profile->router_id;

        $profile->update($request->validated());

        if ($previousRouterId !== $profile->router_id) {
            RemoveHotspotProfileJob::dispatch($previousRouterId, $previousName);
            $previousName = null;
        }

        SyncHotspotProfileJob::dispatch($profile, $previousName);

        Toast::success('Profil hotspot berhasil diperbarui.');

        return back();
    }

    public function destroy(HotspotProfile $profile): RedirectResponse
    {
        if ($profile->vouchers()->exists() || $profile->voucherBatches()->exists()) {
            Toast::error('Profil sudah dipakai voucher. Nonaktifkan profil jika tidak ingin dijual lagi.');

            return back();
        }

        $profile->delete();
        RemoveHotspotProfileJob::dispatch($profile->router_id, $profile->mikrotik_profile);

        Toast::success('Profil hotspot dihapus; profile di router ikut dihapus lewat antrean bila tidak sedang dipakai.');

        return back();
    }
}
