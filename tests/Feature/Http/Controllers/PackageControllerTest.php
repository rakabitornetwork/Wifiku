<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PackageControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_lists_packages_with_router_and_customer_count(): void
    {
        $package = Package::factory()->for(Router::factory())->create();
        Customer::factory()->for($package)->create();

        $this->actingAs($this->admin())->get('/packages')->assertInertia(
            fn (Assert $page) => $page->component('Packages/Index')->where('packages.data.0.customers_count', 1)->has('packages.data.0.router'),
        );
    }

    public function test_creates_package_available_on_all_routers_when_router_is_blank(): void
    {
        $this->actingAs($this->admin())->post('/packages', [
            'router_id' => '',
            'name' => 'Home 20 Mbps',
            'mikrotik_profile' => 'HOME-20',
            'rate_limit' => '20M/20M',
            'price' => 200000,
            'is_active' => true,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('packages', ['name' => 'Home 20 Mbps', 'router_id' => null, 'price' => 200000]);
    }

    public function test_rejects_negative_price_and_unknown_router(): void
    {
        $this->actingAs($this->admin())->post('/packages', [
            'router_id' => 999,
            'name' => 'X',
            'mikrotik_profile' => 'X',
            'price' => -1,
        ])->assertSessionHasErrors(['router_id', 'price']);
    }

    public function test_updates_package(): void
    {
        $package = Package::factory()->create(['price' => 150000]);

        $this->actingAs($this->admin())->put("/packages/{$package->id}", [
            'name' => $package->name,
            'mikrotik_profile' => $package->mikrotik_profile,
            'price' => 175000,
            'is_active' => false,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(175000, $package->fresh()->price);
        $this->assertFalse($package->fresh()->is_active);
    }

    public function test_refuses_to_delete_package_used_by_customers(): void
    {
        $package = Package::factory()->create();
        Customer::factory()->for($package)->create();

        $this->actingAs($this->admin())->delete("/packages/{$package->id}");

        $this->assertNotSoftDeleted($package);
    }

    public function test_deletes_unused_package(): void
    {
        $package = Package::factory()->create();

        $this->actingAs($this->admin())->delete("/packages/{$package->id}");

        $this->assertSoftDeleted($package);
    }
}
