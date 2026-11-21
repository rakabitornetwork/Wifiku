<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $status = AssetStatus::tryFrom((string) $request->query('status'));
        $type = AssetType::tryFrom((string) $request->query('type'));

        $assets = Asset::query()
            ->with('customer:id,customer_code,name')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('mac_address', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderByDesc('id')
            ->paginate($this->perPage(15))
            ->withQueryString();

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
            'filters' => ['search' => $search, 'status' => $status?->value ?? '', 'type' => $type?->value ?? ''],
        ]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        Asset::create($request->validated());

        Toast::success('Perangkat berhasil ditambahkan.');

        return back();
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());

        Toast::success('Perangkat berhasil diperbarui.');

        return back();
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        Toast::success('Perangkat dihapus.');

        return back();
    }
}
