<?php

namespace Tests;

use App\Services\Mikrotik\RouterOsClientFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeRouterOsClientFactory;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Tidak ada test yang boleh menyentuh router, payment gateway, WA gateway, atau GenieACS sungguhan.
        $this->app->instance(RouterOsClientFactory::class, new FakeRouterOsClientFactory);
        Http::preventStrayRequests();
    }
}
