<?php

namespace App\Http\Controllers;

use App\Actions\Voucher\CreateVoucherBatch;
use App\Enums\VoucherBatchStatus;
use App\Enums\VoucherStatus;
use App\Http\Requests\VoucherBatchRequest;
use App\Jobs\Voucher\DeleteVouchersJob;
use App\Models\HotspotProfile;
use App\Models\Reseller;
use App\Models\Router;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Settings\SettingService;
use App\Services\Voucher\VoucherCodeGenerator;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoucherBatchController extends Controller
{
    public function index(Request $request): Response
    {
        $routerId = $request->integer('router_id') ?: null;

        return Inertia::render('Hotspot/Batches/Index', [
            'routers' => Router::orderBy('name')->get(['id', 'name']),
            'routerId' => $routerId ? (string) $routerId : '',
            'routerStats' => $routerId ? Voucher::query()->where('router_id', $routerId)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : null,
            'batches' => VoucherBatch::query()
                ->when($routerId, fn ($query) => $query->where('router_id', $routerId))
                ->with(['router:id,name', 'hotspotProfile:id,name', 'reseller:id,name'])
                ->withCount(['vouchers', 'vouchers as used_count' => fn ($query) => $query->where('status', '!=', VoucherStatus::Unused)])
                ->orderByDesc('id')
                ->paginate($this->perPage(15))
                ->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Hotspot/Batches/Create', [
            'profiles' => HotspotProfile::query()
                ->with('router:id,name')
                ->where('is_active', true)
                ->orderBy('router_id')
                ->orderBy('price')
                ->get(['id', 'router_id', 'name', 'price', 'validity_minutes']),
            'resellers' => Reseller::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'charsets' => array_keys(VoucherCodeGenerator::CHARSETS),
        ]);
    }

    public function store(VoucherBatchRequest $request, CreateVoucherBatch $createVoucherBatch): RedirectResponse
    {
        $batch = $createVoucherBatch->handle($request->validated(), $request->user());

        Toast::success("Batch {$batch->batch_code} dibuat; voucher sedang digenerate di antrean.");

        return to_route('hotspot.batches.show', $batch);
    }

    public function show(Request $request, VoucherBatch $batch): Response
    {
        $batch->load(['router:id,name', 'hotspotProfile:id,name,price,validity_minutes', 'reseller:id,name']);

        $status = VoucherStatus::tryFrom((string) $request->query('status'));

        return Inertia::render('Hotspot/Batches/Show', [
            'batch' => $batch,
            'stats' => $batch->vouchers()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'vouchers' => $batch->vouchers()
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderBy('id')
                ->paginate($this->perPage(50), ['id', 'code', 'password', 'price', 'status', 'first_login_at', 'expires_at'])
                ->withQueryString(),
            'status' => $status?->value ?? '',
        ]);
    }

    /**
     * Hapus voucher satu batch dari router dan database. Voucher terpakai hanya ikut terhapus bila diminta.
     */
    public function destroyVouchers(Request $request, VoucherBatch $batch): RedirectResponse
    {
        $includeUsed = $request->boolean('include_used');

        if (in_array($batch->status, [VoucherBatchStatus::Queued, VoucherBatchStatus::Processing], true)) {
            Toast::error('Batch masih digenerate. Tunggu sampai selesai sebelum menghapus vouchernya.');

            return back();
        }

        DeleteVouchersJob::dispatch($batch->router_id, $batch->id, $includeUsed);

        activity('Voucher')->causedBy($request->user())->withProperties(['batch' => $batch->batch_code, 'include_used' => $includeUsed])->log("Hapus voucher batch {$batch->batch_code}");

        Toast::success('Voucher batch dihapus lewat antrean: dari router lalu dari database.');

        return to_route('hotspot.batches.index');
    }

    /**
     * Hapus voucher seluruh batch milik satu router dari router dan database.
     */
    public function destroyRouterVouchers(Request $request, Router $router): RedirectResponse
    {
        $includeUsed = $request->boolean('include_used');

        DeleteVouchersJob::dispatch($router->id, null, $includeUsed);

        activity('Voucher')->causedBy($request->user())->withProperties(['router' => $router->name, 'include_used' => $includeUsed])->log("Hapus voucher router {$router->name}");

        Toast::success("Voucher router {$router->name} dihapus lewat antrean: dari router lalu dari database.");

        return back();
    }

    /**
     * Halaman cetak mandiri (tanpa sidebar): A4 grid atau thermal 58/80 mm dengan QR auto-login.
     */
    public function print(VoucherBatch $batch, SettingService $settings): Response
    {
        $batch->load(['router:id,name,host', 'hotspotProfile:id,name,price,validity_minutes', 'reseller:id,name,phone']);

        return Inertia::render('Print/Vouchers', [
            'batch' => $batch->only(['id', 'batch_code', 'status']) + [
                'profile' => $batch->hotspotProfile->only(['name', 'price', 'validity_minutes']),
            ],
            'company' => $settings->get('company', 'name', config('app.name')),
            'contact' => $batch->reseller !== null
                ? ['label' => 'Agen', 'name' => $batch->reseller->name, 'phone' => $batch->reseller->phone]
                : ['label' => 'CS', 'name' => null, 'phone' => $settings->get('company', 'cs_whatsapp')],
            'loginUrl' => $settings->get('hotspot', 'login_url') ?: 'http://'.$batch->router->host.'/login',
            'vouchers' => $batch->vouchers()->orderBy('id')->get(['id', 'code', 'password']),
        ]);
    }
}
