<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\OutageStatus;
use App\Enums\WhatsappMessageType;
use App\Jobs\Notification\BroadcastOutageJob;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Outage;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class OutageControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function odpWithCustomers(): Odp
    {
        $odp = Odp::factory()->create(['name' => 'Tiang Balai Desa']);
        Customer::factory()->count(2)->create(['odp_id' => $odp->id, 'router_id' => $odp->router_id]);
        Customer::factory()->create(['odp_id' => $odp->id, 'router_id' => $odp->router_id, 'status' => CustomerStatus::Isolated]);
        Customer::factory()->create(['odp_id' => $odp->id, 'router_id' => $odp->router_id, 'status' => CustomerStatus::Inactive]);
        Customer::factory()->create();

        return $odp;
    }

    public function test_index_lists_open_outages_first_and_recipient_counts(): void
    {
        $odp = $this->odpWithCustomers();
        Outage::factory()->for($odp)->create(['status' => OutageStatus::Resolved, 'title' => 'Lama']);
        Outage::factory()->create(['status' => OutageStatus::Open, 'title' => 'Baru']);

        $this->actingAs($this->userWithRole('Teknisi'))->get('/outages')->assertInertia(
            fn (Assert $page) => $page->component('Outages/Index')
                ->where('outages.data.0.title', 'Baru')
                ->where('odps', fn ($odps) => collect($odps)->firstWhere('id', $odp->id)['recipients_count'] === 3),
        );
    }

    public function test_store_records_the_outage_and_queues_the_broadcast(): void
    {
        Queue::fake([BroadcastOutageJob::class]);
        $odp = $this->odpWithCustomers();
        $technician = $this->userWithRole('Teknisi');

        $this->actingAs($technician)->post('/outages', ['odp_id' => $odp->id, 'title' => 'Fiber putus', 'message' => 'Mohon maaf ada gangguan.'])->assertSessionDoesntHaveErrors();

        $outage = Outage::sole();
        $this->assertSame(OutageStatus::Open, $outage->status);
        $this->assertSame($technician->id, $outage->created_by);
        Queue::assertPushed(BroadcastOutageJob::class, fn ($job) => $job->outage->is($outage) && $job->resolved === false);
    }

    public function test_broadcast_reaches_only_active_and_isolated_customers_of_that_odp(): void
    {
        $odp = $this->odpWithCustomers();

        $this->actingAs($this->admin())->post('/outages', ['odp_id' => $odp->id, 'title' => 'Fiber putus', 'message' => 'Mohon maaf ada gangguan di Tiang Balai Desa.']);

        $messages = WhatsappMessage::where('type', WhatsappMessageType::Outage)->get();
        $this->assertCount(3, $messages);
        $this->assertSame(3, Outage::sole()->notified_count);
        $this->assertEqualsCanonicalizing($odp->customers()->where('status', '!=', 'inactive')->pluck('whatsapp')->all(), $messages->pluck('phone')->all());
        $this->assertStringContainsString('gangguan di Tiang Balai Desa', $messages->first()->body);
        $this->assertStringContainsString('Halo ', $messages->first()->body);
    }

    public function test_second_broadcast_is_refused_while_an_outage_is_open(): void
    {
        $odp = $this->odpWithCustomers();
        $admin = $this->admin();
        $payload = ['odp_id' => $odp->id, 'title' => 'Fiber putus', 'message' => 'Gangguan.'];

        $this->actingAs($admin)->post('/outages', $payload);
        $this->actingAs($admin)->post('/outages', $payload)->assertSessionHasErrors('odp_id');

        $this->assertSame(1, Outage::count());
        $this->assertSame(3, WhatsappMessage::count());
    }

    public function test_a_new_outage_is_allowed_after_the_previous_one_is_resolved(): void
    {
        $odp = $this->odpWithCustomers();
        Outage::factory()->for($odp)->create(['status' => OutageStatus::Resolved]);

        $this->actingAs($this->admin())->post('/outages', ['odp_id' => $odp->id, 'title' => 'Lagi', 'message' => 'Gangguan lagi.'])->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Outage::count());
    }

    public function test_validates_required_fields(): void
    {
        $this->actingAs($this->admin())->post('/outages', ['odp_id' => 999])->assertSessionHasErrors(['odp_id', 'title', 'message']);
    }

    public function test_resolving_with_notification_tells_customers_service_is_back(): void
    {
        $odp = $this->odpWithCustomers();
        $outage = Outage::factory()->for($odp)->create(['title' => 'Fiber putus']);

        $this->actingAs($this->admin())->post("/outages/{$outage->id}/resolve", ['notify' => true]);

        $outage->refresh();
        $this->assertSame(OutageStatus::Resolved, $outage->status);
        $this->assertNotNull($outage->resolved_at);
        $messages = WhatsappMessage::where('type', WhatsappMessageType::Outage)->get();
        $this->assertCount(3, $messages);
        $this->assertStringContainsString('telah teratasi', $messages->first()->body);
    }

    public function test_resolving_without_notification_sends_nothing(): void
    {
        $outage = Outage::factory()->for($this->odpWithCustomers())->create();

        $this->actingAs($this->admin())->post("/outages/{$outage->id}/resolve", ['notify' => false]);

        $this->assertSame(OutageStatus::Resolved, $outage->fresh()->status);
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_resolving_twice_does_not_notify_twice(): void
    {
        $outage = Outage::factory()->for($this->odpWithCustomers())->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/outages/{$outage->id}/resolve", ['notify' => true]);
        $this->actingAs($admin)->post("/outages/{$outage->id}/resolve", ['notify' => true]);

        $this->assertSame(3, WhatsappMessage::count());
    }
}
