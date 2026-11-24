<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResellerRequest;
use App\Models\Reseller;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ResellerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Hotspot/Resellers', [
            'resellers' => Reseller::query()->withCount('voucherBatches')->orderBy('name')->tap(fn ($query) => $this->applySort($query, $this->sortColumns()))->paginate($this->perPage(15))->withQueryString(),
        ]);
    }

    public function store(ResellerRequest $request): RedirectResponse
    {
        Reseller::create($request->validated());

        Toast::success('Reseller berhasil ditambahkan.');

        return back();
    }

    public function update(ResellerRequest $request, Reseller $reseller): RedirectResponse
    {
        $reseller->update($request->validated());

        Toast::success('Reseller berhasil diperbarui.');

        return back();
    }

    public function destroy(Reseller $reseller): RedirectResponse
    {
        $reseller->delete();

        Toast::success('Reseller dihapus. Riwayat batch voucher tetap tersimpan.');

        return back();
    }

    /**
     * @return array<string, string>
     */
    private function sortColumns(): array
    {
        return ['name' => 'name', 'phone' => 'phone', 'discount' => 'discount_percent', 'batches' => 'voucher_batches_count', 'status' => 'is_active'];
    }
}
