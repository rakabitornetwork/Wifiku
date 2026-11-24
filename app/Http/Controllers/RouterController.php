<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouterRequest;
use App\Models\Router;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RouterController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));

        $routers = Router::query()
            ->withCount(['customers', 'packages', 'odps', 'hotspotProfiles'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('host', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->tap(fn ($query) => $this->applySort($query, $this->sortColumns()))
            ->paginate($this->perPage(15))
            ->withQueryString();

        return Inertia::render('Routers/Index', [
            'routers' => $routers,
            'search' => $search,
        ]);
    }

    public function store(RouterRequest $request): RedirectResponse
    {
        Router::create([...$request->payload(), 'webhook_token' => Str::random(40)]);

        Toast::success('Router berhasil ditambahkan.');

        return back();
    }

    public function update(RouterRequest $request, Router $router): RedirectResponse
    {
        $router->update($request->payload());

        Toast::success('Router berhasil diperbarui.');

        return back();
    }

    public function destroy(Router $router): RedirectResponse
    {
        $router->loadCount(['customers', 'packages', 'odps', 'hotspotProfiles']);

        if ($router->customers_count + $router->packages_count + $router->odps_count + $router->hotspot_profiles_count > 0) {
            Toast::error('Router masih dipakai pelanggan, paket, ODP, atau profil hotspot sehingga tidak dapat dihapus.');

            return back();
        }

        $router->delete();

        Toast::success('Router dihapus.');

        return back();
    }

    /**
     * @return array<string, string>
     */
    private function sortColumns(): array
    {
        return [
            'name' => 'name',
            'host' => 'host',
            'status' => 'status',
            'customers' => 'customers_count',
            'packages' => 'packages_count',
            'hotspot_profiles' => 'hotspot_profiles_count',
            'last_checked' => 'last_checked_at',
        ];
    }
}
