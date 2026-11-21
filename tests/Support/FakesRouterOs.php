<?php

namespace Tests\Support;

use App\Models\Router;
use App\Services\Mikrotik\RouterOsClientFactory;

trait FakesRouterOs
{
    /**
     * Router palsu di memori untuk $router (TestCase sudah memasang factory palsu untuk semua test).
     */
    protected function fakeRouterOs(Router $router): FakeRouterOsClient
    {
        /** @var FakeRouterOsClientFactory $factory */
        $factory = $this->app->make(RouterOsClientFactory::class);

        return $factory->for($router);
    }
}
