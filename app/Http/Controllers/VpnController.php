<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VpnController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $serverId = $request->integer('server_id') ?: null;

        $accounts = VpnAccount::query()
            ->with(['server:id,name,public_host', 'member:id,name,email'])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('remote_address', 'like', "%{$search}%")))
            ->when($serverId, fn ($query) => $query->where('vpn_server_id', $serverId))
            ->latest('id')
            ->tap(fn ($query) => $this->applySort($query, [
                'name' => 'name',
                'username' => 'username',
                'protocol' => 'protocol',
                'remote_address' => 'remote_address',
                'status' => 'status',
                'expires_at' => 'expires_at',
                'monthly_price' => 'monthly_price',
            ]))
            ->paginate($this->perPage(15))
            ->withQueryString();

        return Inertia::render('Vpn/Index', [
            'accounts' => $accounts,
            'servers' => VpnServer::query()->with('router:id,name')->withCount('accounts')->orderBy('name')->get(),
            'routers' => Router::orderBy('name')->get(['id', 'name']),
            'search' => $search,
            'serverId' => $serverId ? (string) $serverId : '',
        ]);
    }
}
