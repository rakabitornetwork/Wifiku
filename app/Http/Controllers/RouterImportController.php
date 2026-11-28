<?php

namespace App\Http\Controllers;

use App\Actions\Router\ImportPppSecrets;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RouterImportController extends Controller
{
    /**
     * Baca PPP secret dan profile dari router untuk ditinjau sebelum impor.
     */
    public function preview(Router $router, ImportPppSecrets $import): JsonResponse
    {
        try {
            return response()->json($import->preview($router));
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            return response()->json(['error' => "Gagal membaca router: {$exception->getMessage()}"], 422);
        }
    }

    public function store(Request $request, Router $router, ImportPppSecrets $import): RedirectResponse
    {
        $validated = $request->validate([
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.package' => ['nullable', 'string', 'regex:/^(new|\d+)$/'],
            'mappings.*.price' => ['nullable', 'integer', 'min:0'],
            'billing_day' => ['required', 'integer', 'between:1,31'],
            'due_days' => ['required', 'integer', 'between:1,60'],
            'bill_from' => ['required', 'in:next_cycle,now'],
        ]);

        foreach ($validated['mappings'] as $profile => $mapping) {
            $package = $mapping['package'] ?? '';

            if ($package === 'new' && $router->isIsolirProfile((string) $profile)) {
                return back()->withErrors(["mappings.{$profile}.package" => 'Profile isolir tidak boleh dijadikan paket. Pilih paket asli pelanggan.']);
            }

            if ($package === 'new' && blank($mapping['price'] ?? null)) {
                return back()->withErrors(["mappings.{$profile}.price" => 'Harga paket baru wajib diisi.']);
            }

            if (ctype_digit($package) && ! $router->packages()->whereKey((int) $package)->exists()) {
                return back()->withErrors(["mappings.{$profile}.package" => 'Paket bukan milik router ini.']);
            }

            if (ctype_digit($package) && $router->isIsolirProfile($router->packages()->whereKey((int) $package)->value('mikrotik_profile'))) {
                return back()->withErrors(["mappings.{$profile}.package" => 'Paket ini memakai profile isolir; pilih paket asli pelanggan.']);
            }
        }

        try {
            $report = $import->handle($router, $validated['mappings'], [
                'billing_day' => (int) $validated['billing_day'],
                'due_days' => (int) $validated['due_days'],
                'bill_from' => $validated['bill_from'],
            ]);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            Toast::error("Gagal membaca router: {$exception->getMessage()}");

            return back();
        }

        activity('Router')->performedOn($router)->causedBy($request->user())->withProperties($report)->log("Impor pelanggan dari router {$router->name}");

        Toast::success("{$report['imported']} pelanggan diimpor, {$report['existing']} sudah ada, {$report['skipped_profile']} dilewati (profile tidak dipetakan), {$report['skipped_no_password']} dilewati (tanpa password).");

        return back();
    }
}
