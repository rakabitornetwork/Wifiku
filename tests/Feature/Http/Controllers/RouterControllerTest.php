<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class RouterControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'POP Desa Sukamaju',
            'host' => '10.10.0.2',
            'api_port' => 8728,
            'use_ssl' => false,
            'username' => 'api-user',
            'password' => 'rahasia-router',
            'isolir_profile' => 'ISOLIR',
            ...$overrides,
        ];
    }

    public function test_guest_cannot_list_routers(): void
    {
        $this->get('/routers')->assertRedirect('/login');
    }

    public function test_lists_routers_with_usage_counts_without_leaking_passwords(): void
    {
        $router = Router::factory()->create(['name' => 'POP A']);
        Customer::factory()->for($router)->create();

        $this->actingAs($this->admin())->get('/routers')->assertInertia(
            fn (Assert $page) => $page
                ->component('Routers/Index')
                ->has('routers.data', 1)
                ->where('routers.data.0.customers_count', 1)
                ->missing('routers.data.0.password'),
        );
    }

    public function test_search_filters_routers_by_name_or_host(): void
    {
        Router::factory()->create(['name' => 'POP Utara']);
        Router::factory()->create(['name' => 'POP Selatan']);

        $this->actingAs($this->admin())->get('/routers?search=Utara')->assertInertia(
            fn (Assert $page) => $page->has('routers.data', 1)->where('routers.data.0.name', 'POP Utara'),
        );
    }

    public function test_creates_router_and_stores_password_encrypted(): void
    {
        $this->actingAs($this->admin())->post('/routers', $this->validPayload())->assertRedirect();

        $router = Router::sole();
        $this->assertSame('rahasia-router', $router->password);
        $this->assertNotSame('rahasia-router', DB::table('routers')->value('password'));
    }

    public function test_rejects_same_host_and_port_but_allows_same_host_on_another_port(): void
    {
        Router::factory()->create(['host' => '10.10.0.2', 'api_port' => 8728]);

        $this->actingAs($this->admin())->post('/routers', $this->validPayload())->assertSessionHasErrors('host');
        $this->actingAs($this->admin())->post('/routers', $this->validPayload(['api_port' => 8729]))->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('routers', 2);
    }

    public function test_requires_password_on_create(): void
    {
        $this->actingAs($this->admin())->post('/routers', $this->validPayload(['password' => '']))->assertSessionHasErrors('password');
    }

    public function test_blank_password_on_update_keeps_the_stored_password(): void
    {
        $router = Router::factory()->create(['password' => 'lama']);

        $this->actingAs($this->admin())
            ->put("/routers/{$router->id}", $this->validPayload(['name' => 'Nama Baru', 'host' => $router->host, 'password' => '']))
            ->assertSessionDoesntHaveErrors();

        $router->refresh();
        $this->assertSame('Nama Baru', $router->name);
        $this->assertSame('lama', $router->password);
    }

    public function test_new_password_on_update_replaces_the_stored_password(): void
    {
        $router = Router::factory()->create(['password' => 'lama']);

        $this->actingAs($this->admin())->put("/routers/{$router->id}", $this->validPayload(['host' => $router->host, 'password' => 'baru']));

        $this->assertSame('baru', $router->fresh()->password);
    }

    public function test_deletes_unused_router(): void
    {
        $router = Router::factory()->create();

        $this->actingAs($this->admin())->delete("/routers/{$router->id}")->assertRedirect();

        $this->assertSoftDeleted($router);
    }

    public function test_refuses_to_delete_router_that_still_has_customers(): void
    {
        $router = Router::factory()->create();
        Customer::factory()->for($router)->create();

        $this->actingAs($this->admin())->delete("/routers/{$router->id}")->assertRedirect();

        $this->assertNotSoftDeleted($router);
    }
}
