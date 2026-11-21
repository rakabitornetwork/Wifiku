<?php

namespace App\Services\Mikrotik;

use App\Models\Router;

/**
 * Pembuat koneksi per router. Test menggantinya lewat container dengan klien palsu.
 */
class RouterOsClientFactory
{
    public function make(Router $router): RouterOsClient
    {
        return new RouterOsApiClient($router);
    }
}
