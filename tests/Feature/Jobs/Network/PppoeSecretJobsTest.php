<?php

namespace Tests\Feature\Jobs\Network;

use App\Jobs\Network\RemovePppoeSecretJob;
use App\Jobs\Network\SyncPppoeSecretJob;
use App\Models\Customer;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class PppoeSecretJobsTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    public function test_removal_job_deletes_secret_and_active_session_from_router(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $fake->tables['/ppp/secret'] = [['.id' => '*1', 'name' => 'budi'], ['.id' => '*2', 'name' => 'siti']];
        $fake->tables['/ppp/active'] = [['.id' => '*a1', 'name' => 'budi']];

        (new RemovePppoeSecretJob($router->id, 'budi'))->handle();

        $this->assertSame(['siti'], array_column($fake->tables['/ppp/secret'], 'name'));
        $this->assertSame([], $fake->tables['/ppp/active']);
    }

    public function test_removal_job_is_a_noop_when_secret_is_already_gone_or_router_missing(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);

        (new RemovePppoeSecretJob($router->id, 'tidak-ada'))->handle();
        (new RemovePppoeSecretJob(999999, 'budi'))->handle();

        $this->assertSame([], array_values(array_filter($fake->commands, fn (array $command) => str_ends_with($command['path'], '/remove'))));
    }

    public function test_removal_job_propagates_router_failure_so_it_is_retried(): void
    {
        $router = Router::factory()->create();
        $this->fakeRouterOs($router)->failWith = new MikrotikConnectionException('timeout');

        $this->expectException(MikrotikConnectionException::class);

        (new RemovePppoeSecretJob($router->id, 'budi'))->handle();
    }

    public function test_sync_job_does_not_recreate_secret_of_deleted_customer(): void
    {
        $customer = Customer::factory()->create();
        $fake = $this->fakeRouterOs($customer->router);
        $customer->delete();

        (new SyncPppoeSecretJob($customer))->handle();

        $this->assertSame([], $fake->commands);
    }
}
