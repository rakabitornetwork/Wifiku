<?php

namespace App\Http\Controllers;

use App\Http\Requests\OdpRequest;
use App\Models\Odp;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;

class OdpController extends Controller
{
    public function store(OdpRequest $request): RedirectResponse
    {
        Odp::create($request->validated());

        Toast::success('ODP berhasil ditambahkan.');

        return back();
    }

    public function update(OdpRequest $request, Odp $odp): RedirectResponse
    {
        $odp->update($request->validated());

        Toast::success('ODP berhasil diperbarui.');

        return back();
    }

    public function destroy(Odp $odp): RedirectResponse
    {
        if ($odp->customers()->exists() || $odp->children()->exists() || $odp->outages()->exists()) {
            Toast::error('ODP masih memiliki pelanggan, ODP turunan, atau riwayat gangguan sehingga tidak dapat dihapus.');

            return back();
        }

        $odp->delete();

        Toast::success('ODP dihapus.');

        return back();
    }
}
