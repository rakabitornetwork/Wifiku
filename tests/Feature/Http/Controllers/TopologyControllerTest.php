<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\OutageStatus;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Outage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class TopologyControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_reports_port_usage_and_open_outages_per_odp(): void
    {
        $odp = Odp::factory()->create(['total_ports' => 8]);
        Customer::factory()->count(3)->create(['odp_id' => $odp->id, 'router_id' => $odp->router_id]);
        Outage::factory()->for($odp)->create(['status' => OutageStatus::Open]);
        Outage::factory()->for($odp)->create(['status' => OutageStatus::Resolved]);

        $this->actingAs($this->userWithRole('Teknisi'))->get('/topology')->assertInertia(
            fn (Assert $page) => $page->component('Topology/Index')->where('odps.0.customers_count', 3)->where('odps.0.open_outages_count', 1)->has('odps.0.router'),
        );
    }

    public function test_map_pins_only_customers_with_coordinates_who_are_not_inactive(): void
    {
        $odp = Odp::factory()->create();
        Customer::factory()->create(['odp_id' => $odp->id, 'latitude' => -7.25, 'longitude' => 112.75, 'name' => 'Terlihat']);
        Customer::factory()->create(['latitude' => -7.3, 'longitude' => 112.8, 'status' => CustomerStatus::Isolated, 'name' => 'Isolir']);
        Customer::factory()->create(['latitude' => -7.3, 'longitude' => 112.8, 'status' => CustomerStatus::Inactive]);
        Customer::factory()->create(['latitude' => null, 'longitude' => null]);

        $this->actingAs($this->admin())->get('/topology')->assertInertia(
            fn (Assert $page) => $page->has('customers', 2)->where('customers.0.odp_id', $odp->id)->missing('customers.0.whatsapp')->missing('customers.0.address'),
        );
    }
}
