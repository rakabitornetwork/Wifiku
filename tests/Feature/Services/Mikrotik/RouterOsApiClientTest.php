<?php

namespace Tests\Feature\Services\Mikrotik;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Mikrotik\RouterOsApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouterOsApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_refused_connection_becomes_connection_exception_quickly(): void
    {
        // Port 1 di localhost tertutup: koneksi langsung ditolak tanpa menunggu timeout.
        $router = Router::factory()->make(['name' => 'POP Uji', 'host' => '127.0.0.1', 'api_port' => 1]);
        $started = microtime(true);

        try {
            (new RouterOsApiClient($router))->command('/system/identity/print');
            $this->fail('Expected a connection exception.');
        } catch (MikrotikConnectionException $exception) {
            $this->assertStringContainsString('POP Uji', $exception->getMessage());
        }

        $this->assertLessThan(6, microtime(true) - $started);
    }
}
