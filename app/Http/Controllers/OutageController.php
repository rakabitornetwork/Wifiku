<?php

namespace App\Http\Controllers;

use App\Actions\Outage\BroadcastOutage;
use App\Actions\Outage\ResolveOutage;
use App\Enums\CustomerStatus;
use App\Http\Requests\OutageRequest;
use App\Models\Odp;
use App\Models\Outage;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Outages/Index', [
            'outages' => Outage::query()
                ->with(['odp:id,name,code', 'creator:id,name'])
                ->orderByRaw("case status when 'open' then 0 else 1 end")
                ->orderByDesc('id')
                ->paginate($this->perPage(15))
                ->withQueryString(),
            'odps' => Odp::query()
                ->withCount(['customers as recipients_count' => fn ($query) => $query->where('status', '!=', CustomerStatus::Inactive)])
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(OutageRequest $request, BroadcastOutage $broadcastOutage): RedirectResponse
    {
        $outage = $broadcastOutage->handle(Odp::findOrFail($request->integer('odp_id')), $request->string('title')->toString(), $request->string('message')->toString(), $request->user());

        Toast::success("Gangguan dicatat; pengumuman WhatsApp ke pelanggan {$outage->odp->name} sedang dikirim.");

        return back();
    }

    public function resolve(Request $request, Outage $outage, ResolveOutage $resolveOutage): RedirectResponse
    {
        $resolveOutage->handle($outage, $request->boolean('notify'));

        Toast::success('Gangguan ditandai selesai.');

        return back();
    }
}
