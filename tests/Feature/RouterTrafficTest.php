<?php

namespace Tests\Feature;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Mikrotik\RouterOsClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class RouterTrafficTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    public function test_interfaces_list_hides_dynamic_sessions_and_disabled_interfaces(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $fake->seed('/interface', ['name' => 'ether1-wan', 'type' => 'ether', 'running' => 'true', 'disabled' => 'false', 'dynamic' => 'false', 'comment' => 'Uplink ISP']);
        $fake->seed('/interface', ['name' => 'bridge-lan', 'type' => 'bridge', 'running' => 'true', 'disabled' => 'false', 'dynamic' => 'false']);
        $fake->seed('/interface', ['name' => 'ether5', 'type' => 'ether', 'running' => 'false', 'disabled' => 'true', 'dynamic' => 'false']);
        $fake->seed('/interface', ['name' => '<pppoe-budi@desa>', 'type' => 'pppoe-in', 'running' => 'true', 'disabled' => 'false', 'dynamic' => 'true']);

        $this->actingAs($this->admin())->getJson("/routers/{$router->id}/traffic/interfaces")->assertOk()->assertExactJson(['interfaces' => [
            ['name' => 'ether1-wan', 'type' => 'ether', 'running' => true, 'comment' => 'Uplink ISP'],
            ['name' => 'bridge-lan', 'type' => 'bridge', 'running' => true, 'comment' => null],
        ]]);
    }

    public function test_sample_returns_live_rates_from_the_router_for_the_chosen_interface(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $fake->responses['/interface/monitor-traffic'] = [['name' => 'ether1-wan', 'rx-bits-per-second' => '24500000', 'tx-bits-per-second' => '3100000', 'rx-packets-per-second' => '2100', 'tx-packets-per-second' => '900']];

        $this->actingAs($this->admin())->getJson("/routers/{$router->id}/traffic?interface=ether1-wan")->assertOk()->assertJson([
            'interface' => 'ether1-wan', 'rx_bps' => 24500000, 'tx_bps' => 3100000, 'rx_pps' => 2100, 'tx_pps' => 900,
        ])->assertJsonStructure(['at']);

        $command = collect($fake->commands)->firstWhere('path', '/interface/monitor-traffic');
        $this->assertSame(['interface' => 'ether1-wan', 'once' => ''], $command['attributes']);
    }

    public function test_interface_names_with_control_characters_or_attribute_separators_are_rejected(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $admin = $this->admin();

        foreach (["ether1\n=once=", 'ether1=once', str_repeat('a', 65), '', 'eth;1'] as $name) {
            $this->actingAs($admin)->getJson("/routers/{$router->id}/traffic?".http_build_query(['interface' => $name]))->assertStatus(422)->assertJsonValidationErrors('interface');
        }

        $this->assertSame([], $fake->commands);
    }

    public function test_router_failures_become_a_422_with_a_readable_message(): void
    {
        $router = Router::factory()->create();
        $this->fakeRouterOs($router)->failWith = new MikrotikConnectionException('Tidak dapat terhubung ke Router A');

        $this->actingAs($this->admin())->getJson("/routers/{$router->id}/traffic?interface=ether1")->assertStatus(422)->assertJson(['error' => 'Tidak dapat terhubung ke Router A']);
        $this->actingAs($this->admin())->getJson("/routers/{$router->id}/traffic/interfaces")->assertStatus(422);
    }

    public function test_only_router_managers_can_watch_traffic(): void
    {
        $router = Router::factory()->create();
        $this->fakeRouterOs($router);

        $this->getJson("/routers/{$router->id}/traffic?interface=ether1")->assertUnauthorized();
        $this->actingAs($this->userWithRole('Teknisi'))->getJson("/routers/{$router->id}/traffic?interface=ether1")->assertForbidden();
        $this->actingAs($this->userWithRole('Teknisi'))->getJson("/routers/{$router->id}/traffic/interfaces")->assertForbidden();
    }

    public function test_polling_is_throttled_separately_from_other_router_actions(): void
    {
        $router = Router::factory()->create();
        $this->fakeRouterOs($router)->responses['/interface/monitor-traffic'] = [['rx-bits-per-second' => '1']];
        $admin = $this->admin();

        for ($i = 0; $i < 120; $i++) {
            $this->actingAs($admin)->getJson("/routers/{$router->id}/traffic?interface=ether1")->assertOk();
        }

        $this->actingAs($admin)->getJson("/routers/{$router->id}/traffic?interface=ether1")->assertStatus(429);
        $this->actingAs($admin)->getJson("/routers/{$router->id}/traffic/interfaces")->assertOk();
    }

    public function test_the_stream_reuses_one_router_connection_for_all_samples_and_ends_cleanly(): void
    {
        config(['traffic.interval_ms' => 0, 'traffic.max_samples' => 5]);
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $fake->responses['/interface/monitor-traffic'] = [['rx-bits-per-second' => '8000000', 'tx-bits-per-second' => '1000000']];

        $response = $this->actingAs($this->admin())->get("/routers/{$router->id}/traffic/stream?interface=ether1");
        $body = $response->streamedContent();

        $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $this->assertSame(5, substr_count($body, "event: sample\n"));
        $this->assertStringContainsString('"rx_bps":8000000', $body);
        $this->assertStringEndsWith("event: end\ndata: []\n\n", $body);
        $this->assertSame(5, collect($fake->commands)->where('path', '/interface/monitor-traffic')->count());
        $this->assertSame(1, $this->app->make(RouterOsClientFactory::class)->made);
    }

    public function test_a_stream_that_cannot_reach_the_router_is_a_normal_422_and_frees_its_slot(): void
    {
        config(['traffic.max_streams' => 1]);
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $fake->failWith = new MikrotikConnectionException('Router tidak terjangkau');
        $admin = $this->admin();

        $this->actingAs($admin)->getJson("/routers/{$router->id}/traffic/stream?interface=ether1")->assertStatus(422)->assertJson(['error' => 'Router tidak terjangkau']);

        $fake->failWith = null;
        $fake->responses['/interface/monitor-traffic'] = [['rx-bits-per-second' => '1']];
        config(['traffic.max_samples' => 1]);

        $this->actingAs($admin)->get("/routers/{$router->id}/traffic/stream?interface=ether1")->assertOk()->streamedContent();
    }

    public function test_a_failure_in_the_middle_of_a_stream_is_reported_as_an_event(): void
    {
        config(['traffic.interval_ms' => 0]);
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $fake->responses['/interface/monitor-traffic'] = [['rx-bits-per-second' => '5']];

        $response = $this->actingAs($this->admin())->get("/routers/{$router->id}/traffic/stream?interface=ether1");
        $fake->failWith = new MikrotikConnectionException('Koneksi putus');
        $body = $response->streamedContent();

        $this->assertSame(1, substr_count($body, "event: sample\n"));
        $this->assertStringContainsString("event: failure\ndata: {\"error\":\"Koneksi putus\"}", $body);
        $this->assertStringNotContainsString('event: end', $body);
    }

    public function test_simultaneous_streams_are_capped_and_the_count_returns_to_zero_afterwards(): void
    {
        config(['traffic.max_streams' => 2, 'traffic.max_samples' => 1, 'traffic.interval_ms' => 0]);
        $router = Router::factory()->create();
        $this->fakeRouterOs($router)->responses['/interface/monitor-traffic'] = [['rx-bits-per-second' => '1']];
        $admin = $this->admin();

        Cache::put('router-traffic:open-streams', 2, 60);
        $this->actingAs($admin)->getJson("/routers/{$router->id}/traffic/stream?interface=ether1")->assertStatus(429);

        Cache::put('router-traffic:open-streams', 0, 60);
        $this->actingAs($admin)->get("/routers/{$router->id}/traffic/stream?interface=ether1")->streamedContent();
        $this->assertSame(0, (int) Cache::get('router-traffic:open-streams'));
    }
}
