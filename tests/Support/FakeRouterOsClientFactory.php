<?php

namespace Tests\Support;

use App\Models\Router;
use App\Services\Mikrotik\RouterOsClient;
use App\Services\Mikrotik\RouterOsClientFactory;

class FakeRouterOsClientFactory extends RouterOsClientFactory
{
    /** @var array<int, FakeRouterOsClient> */
    public array $clients = [];

    /** Berapa kali koneksi dibuat; dipakai untuk memastikan koneksi dipakai ulang. */
    public int $made = 0;

    public function make(Router $router): RouterOsClient
    {
        $this->made++;

        return $this->for($router);
    }

    public function for(Router $router): FakeRouterOsClient
    {
        return $this->clients[$router->id] ??= new FakeRouterOsClient;
    }
}
