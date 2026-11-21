<?php

namespace Tests\Support;

use App\Models\Router;
use App\Services\Mikrotik\RouterOsClient;
use App\Services\Mikrotik\RouterOsClientFactory;

class FakeRouterOsClientFactory extends RouterOsClientFactory
{
    /** @var array<int, FakeRouterOsClient> */
    public array $clients = [];

    public function make(Router $router): RouterOsClient
    {
        return $this->for($router);
    }

    public function for(Router $router): FakeRouterOsClient
    {
        return $this->clients[$router->id] ??= new FakeRouterOsClient;
    }
}
