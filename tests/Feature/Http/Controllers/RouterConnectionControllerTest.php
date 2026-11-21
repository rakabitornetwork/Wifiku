<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\RouterStatus;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class RouterConnectionControllerTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    public function test_check_marks_reachable_router_online(): void
    {
        $router = Router::factory()->create(['status' => RouterStatus::Unknown]);
        $this->fakeRouterOs($router);

        $this->actingAs($this->admin())->post("/routers/{$router->id}/check")->assertRedirect();

        $this->assertSame(RouterStatus::Online, $router->fresh()->status);
        $this->assertNotNull($router->fresh()->last_checked_at);
        $this->assertNull($router->fresh()->last_error);
    }

    public function test_check_marks_unreachable_router_offline_with_error(): void
    {
        $router = Router::factory()->create();
        $this->fakeRouterOs($router)->failWith = new MikrotikConnectionException('Connection refused');

        $this->actingAs($this->admin())->post("/routers/{$router->id}/check");

        $this->assertSame(RouterStatus::Offline, $router->fresh()->status);
        $this->assertSame('Connection refused', $router->fresh()->last_error);
    }

    public function test_new_router_gets_a_webhook_token_used_in_its_hotspot_script(): void
    {
        $this->actingAs($this->admin())->post('/routers', [
            'name' => 'POP A', 'host' => '10.0.0.1', 'api_port' => 8728, 'username' => 'api', 'password' => 'x', 'isolir_profile' => 'ISOLIR',
        ]);
        $router = Router::sole();

        $script = $this->actingAs($this->admin())->getJson("/routers/{$router->id}/hotspot-script")->assertOk()->json('script');

        $this->assertSame(40, strlen($router->webhook_token));
        $this->assertStringContainsString(route('webhooks.hotspot-login', $router).'?token='.$router->webhook_token, $script);
        $this->assertStringContainsString('$user', $script);
    }

    public function test_hotspot_script_backfills_token_for_existing_router(): void
    {
        $router = Router::factory()->create(['webhook_token' => null]);

        $this->actingAs($this->admin())->getJson("/routers/{$router->id}/hotspot-script")->assertOk();

        $this->assertNotNull($router->fresh()->webhook_token);
    }

    public function test_router_listing_never_exposes_the_webhook_token(): void
    {
        Router::factory()->create(['webhook_token' => 'rahasia-token']);

        $this->assertStringNotContainsString('rahasia-token', $this->actingAs($this->admin())->get('/routers')->getContent());
    }
}
