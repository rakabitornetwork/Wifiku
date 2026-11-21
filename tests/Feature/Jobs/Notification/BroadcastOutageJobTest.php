<?php

namespace Tests\Feature\Jobs\Notification;

use App\Jobs\Notification\BroadcastOutageJob;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Outage;
use App\Models\Package;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastOutageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handles_more_customers_than_one_chunk(): void
    {
        $odp = Odp::factory()->create();
        Customer::factory()->count(250)->create(['odp_id' => $odp->id, 'router_id' => $odp->router_id, 'package_id' => Package::factory()->create()->id]);
        $outage = Outage::factory()->for($odp)->create();

        $this->app->call([new BroadcastOutageJob($outage), 'handle']);

        $this->assertSame(250, WhatsappMessage::count());
        $this->assertSame(250, $outage->fresh()->notified_count);
    }

    public function test_resolution_broadcast_keeps_the_original_notified_count(): void
    {
        $odp = Odp::factory()->create();
        Customer::factory()->count(2)->create(['odp_id' => $odp->id, 'router_id' => $odp->router_id]);
        $outage = Outage::factory()->for($odp)->create(['notified_count' => 7]);

        $this->app->call([new BroadcastOutageJob($outage, resolved: true), 'handle']);

        $this->assertSame(7, $outage->fresh()->notified_count);
        $this->assertSame(2, WhatsappMessage::count());
    }
}
