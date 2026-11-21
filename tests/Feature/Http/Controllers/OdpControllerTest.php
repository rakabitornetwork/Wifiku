<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\Odp;
use App\Models\Outage;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class OdpControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(Router $router, array $overrides = []): array
    {
        return ['router_id' => $router->id, 'name' => 'Tiang Balai Desa', 'code' => 'ODP-001', 'latitude' => -7.25, 'longitude' => 112.75, 'total_ports' => 8, ...$overrides];
    }

    public function test_creates_odp_with_coordinates(): void
    {
        $router = Router::factory()->create();

        $this->actingAs($this->admin())->post('/odps', $this->payload($router))->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('odps', ['code' => 'ODP-001', 'router_id' => $router->id, 'total_ports' => 8]);
    }

    public function test_code_must_be_unique_but_may_be_kept_on_update(): void
    {
        $router = Router::factory()->create();
        $odp = Odp::factory()->for($router)->create(['code' => 'ODP-001']);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/odps', $this->payload($router))->assertSessionHasErrors('code');
        $this->actingAs($admin)->put("/odps/{$odp->id}", $this->payload($router, ['name' => 'Nama Baru']))->assertSessionDoesntHaveErrors();
        $this->assertSame('Nama Baru', $odp->fresh()->name);
    }

    public function test_latitude_and_longitude_must_be_given_together_and_in_range(): void
    {
        $router = Router::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/odps', $this->payload($router, ['longitude' => null]))->assertSessionHasErrors('longitude');
        $this->actingAs($admin)->post('/odps', $this->payload($router, ['latitude' => 95]))->assertSessionHasErrors('latitude');
        $this->actingAs($admin)->post('/odps', $this->payload($router, ['latitude' => null, 'longitude' => null]))->assertSessionDoesntHaveErrors();
    }

    public function test_capacity_cannot_drop_below_a_port_already_in_use(): void
    {
        $router = Router::factory()->create();
        $odp = Odp::factory()->for($router)->create(['total_ports' => 8]);
        Customer::factory()->for($router)->create(['odp_id' => $odp->id, 'odp_port' => 6]);

        $this->actingAs($this->admin())->put("/odps/{$odp->id}", $this->payload($router, ['code' => $odp->code, 'total_ports' => 4]))->assertSessionHasErrors('total_ports');
        $this->actingAs($this->admin())->put("/odps/{$odp->id}", $this->payload($router, ['code' => $odp->code, 'total_ports' => 6]))->assertSessionDoesntHaveErrors();
    }

    public function test_odp_cannot_be_its_own_parent(): void
    {
        $odp = Odp::factory()->create();

        $this->actingAs($this->admin())->put("/odps/{$odp->id}", $this->payload($odp->router, ['code' => $odp->code, 'parent_odp_id' => $odp->id]))->assertSessionHasErrors('parent_odp_id');
    }

    public function test_deletes_unused_odp(): void
    {
        $odp = Odp::factory()->create();

        $this->actingAs($this->admin())->delete("/odps/{$odp->id}");

        $this->assertModelMissing($odp);
    }

    public function test_refuses_to_delete_odp_with_customers_children_or_outage_history(): void
    {
        $admin = $this->admin();
        $withCustomer = Odp::factory()->create();
        Customer::factory()->create(['odp_id' => $withCustomer->id, 'router_id' => $withCustomer->router_id]);
        $parent = Odp::factory()->create();
        Odp::factory()->create(['parent_odp_id' => $parent->id]);
        $withOutage = Odp::factory()->create();
        Outage::factory()->for($withOutage)->create();

        foreach ([$withCustomer, $parent, $withOutage] as $odp) {
            $this->actingAs($admin)->delete("/odps/{$odp->id}");
            $this->assertModelExists($odp);
        }
    }

    public function test_technician_cannot_edit_the_topology(): void
    {
        $odp = Odp::factory()->create();

        $this->actingAs($this->userWithRole('Teknisi'))->delete("/odps/{$odp->id}")->assertForbidden();

        $this->assertModelExists($odp);
    }
}
