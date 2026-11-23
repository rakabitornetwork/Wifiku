<?php

namespace App\Http\Controllers;

use App\Actions\Router\CheckRouterConnection;
use App\Models\Router;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class RouterConnectionController extends Controller
{
    /**
     * Uji koneksi API satu router sekarang juga (timeout 5 detik).
     */
    public function check(Router $router, CheckRouterConnection $checkRouterConnection): RedirectResponse
    {
        if ($checkRouterConnection->handle($router)) {
            Toast::success("{$router->name} online.");
        } else {
            Toast::error("{$router->name} offline: {$router->last_error}");
        }

        return back();
    }

    /**
     * Script on-login untuk Hotspot User Profile, berisi URL webhook dan token rahasia router ini.
     */
    public function hotspotScript(Router $router): JsonResponse
    {
        return response()->json([
            'script' => $router->hotspotLoginScript(),
        ]);
    }
}
