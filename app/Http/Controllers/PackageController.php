<?php

namespace App\Http\Controllers;

use App\Http\Requests\PackageRequest;
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

        $packages = Package::query()
            ->with('router:id,name')
            ->withCount('customers')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('price')
            ->orderBy('name')
            ->paginate($this->perPage(15))
            ->withQueryString();

        return Inertia::render('Packages/Index', [
            'packages' => $packages,
            'routers' => Router::orderBy('name')->get(['id', 'name']),
            'search' => $search,
        ]);
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        Package::create($request->validated());

        Toast::success('Paket berhasil ditambahkan.');

        return back();
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $package->update($request->validated());

        Toast::success('Paket berhasil diperbarui.');

        return back();
    }

    public function destroy(Package $package): RedirectResponse
    {
        if ($package->customers()->exists()) {
            Toast::error('Paket masih dipakai pelanggan. Nonaktifkan paket jika tidak ingin dijual lagi.');

            return back();
        }

        $package->delete();

        Toast::success('Paket dihapus.');

        return back();
    }
}
