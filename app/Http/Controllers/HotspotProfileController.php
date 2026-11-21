<?php

namespace App\Http\Controllers;

use App\Http\Requests\HotspotProfileRequest;
use App\Models\HotspotProfile;
use App\Models\Router;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
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

    public function store(HotspotProfileRequest $request): RedirectResponse
    {
        HotspotProfile::create($request->validated());

        Toast::success('Profil hotspot berhasil ditambahkan.');

        return back();
    }

    public function update(HotspotProfileRequest $request, HotspotProfile $profile): RedirectResponse
    {
        $profile->update($request->validated());

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

        Toast::success('Profil hotspot dihapus.');

        return back();
    }
}
