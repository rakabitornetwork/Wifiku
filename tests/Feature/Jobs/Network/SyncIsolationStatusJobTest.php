<?php

namespace Tests\Feature\Jobs\Network;

use App\Enums\CustomerStatus;
use App\Jobs\Network\SyncIsolationStatusJob;
use App\Models\Customer;
use App\Models\Router;
use App\Models\WhatsappMessage;
use App\Services\Mikrotik\MikrotikConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeRouterOsClient;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class SyncIsolationStatusJobTest extends TestCase
{
    use FakesRouterOs;
    use RefreshDatabase;

    private Router $router;

    /**
     * @param  array<string, string>  $secrets  username => profile
     */
    private function secretsOnRouter(array $secrets, ?Router $router = null): FakeRouterOsClient
    {
        $fake = $this->fakeRouterOs($router ?? $this->router);
        $fake->tables['/ppp/secret'] = collect($secrets)->map(fn (string $profile, string $name) => ['.id' => '*'.$name, 'name' => $name, 'profile' => $profile])->values()->all();

        return $fake;
    }

    private function customer(string $username, CustomerStatus $status, ?Router $router = null): Customer
    {
        $customer = Customer::factory()->for($router ?? $this->router)->create([
            'pppoe_username' => $username,
            'status' => $status,
            'isolated_at' => $status === CustomerStatus::Isolated ? now()->subDays(3) : null,
        ]);

        // Perubahan lama: sudah lewat masa tunggu sinkronisasi.
        $customer->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        return $customer;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Router::factory()->create(['isolir_profile' => 'ISOLIR']);
    }

    public function test_active_customers_in_router_isolir_profile_become_isolated_without_notification(): void
    {
        Queue::fake();
        $fake = $this->secretsOnRouter(['budi' => 'ISOLIR', 'siti' => '10M', 'andi' => 'ISOLIR']);
        $budi = $this->customer('budi', CustomerStatus::Active);
        $siti = $this->customer('siti', CustomerStatus::Active);
        $andi = $this->customer('andi', CustomerStatus::Inactive);
        $otherRouter = $this->customer('budi', CustomerStatus::Active, Router::factory()->create());

        (new SyncIsolationStatusJob($this->router))->handle();

        $this->assertSame(CustomerStatus::Isolated, $budi->fresh()->status);
        $this->assertNotNull($budi->fresh()->isolated_at);
        $this->assertSame(CustomerStatus::Active, $siti->fresh()->status);
        $this->assertSame(CustomerStatus::Inactive, $andi->fresh()->status);
        $this->assertSame(CustomerStatus::Active, $otherRouter->fresh()->status);
        $this->assertSame(0, WhatsappMessage::count());
        $this->assertSame([], array_values(array_filter($fake->commands, fn (array $command) => ! str_ends_with($command['path'], '/print'))));
    }

    public function test_isolated_customers_back_in_a_normal_profile_become_active(): void
    {
        $this->secretsOnRouter(['budi' => '10M', 'siti' => 'ISOLIR', 'rina' => '20M']);
        $budi = $this->customer('budi', CustomerStatus::Isolated);
        $siti = $this->customer('siti', CustomerStatus::Isolated);
        $rina = $this->customer('rina', CustomerStatus::Inactive);

        (new SyncIsolationStatusJob($this->router))->handle();

        $this->assertSame(CustomerStatus::Active, $budi->fresh()->status);
        $this->assertNull($budi->fresh()->isolated_at);
        $this->assertSame(CustomerStatus::Isolated, $siti->fresh()->status);
        $this->assertSame(CustomerStatus::Inactive, $rina->fresh()->status);
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_isolir_profile_name_is_matched_ignoring_case(): void
    {
        $this->secretsOnRouter(['budi' => 'isolir', 'siti' => 'Isolir']);
        $budi = $this->customer('budi', CustomerStatus::Active);
        $siti = $this->customer('siti', CustomerStatus::Isolated);

        (new SyncIsolationStatusJob($this->router))->handle();

        $this->assertSame(CustomerStatus::Isolated, $budi->fresh()->status);
        $this->assertSame(CustomerStatus::Isolated, $siti->fresh()->status);
    }

    public function test_customers_missing_from_the_router_are_left_alone(): void
    {
        $this->secretsOnRouter(['lain' => 'ISOLIR']);
        $active = $this->customer('budi', CustomerStatus::Active);
        $isolated = $this->customer('siti', CustomerStatus::Isolated);

        (new SyncIsolationStatusJob($this->router))->handle();

        $this->assertSame(CustomerStatus::Active, $active->fresh()->status);
        $this->assertSame(CustomerStatus::Isolated, $isolated->fresh()->status);
    }

    public function test_recently_changed_customers_are_skipped_so_pending_form_changes_are_not_undone(): void
    {
        $this->secretsOnRouter(['budi' => '10M', 'siti' => 'ISOLIR']);
        $reopenedInForm = $this->customer('budi', CustomerStatus::Active);
        $isolatedInForm = $this->customer('siti', CustomerStatus::Isolated);
        $reopenedInForm->update(['status' => CustomerStatus::Isolated]);
        $isolatedInForm->update(['status' => CustomerStatus::Active]);

        (new SyncIsolationStatusJob($this->router))->handle();

        $this->assertSame(CustomerStatus::Isolated, $reopenedInForm->fresh()->status);
        $this->assertSame(CustomerStatus::Active, $isolatedInForm->fresh()->status);

        $this->travel(SyncIsolationStatusJob::SETTLE_MINUTES + 1)->minutes();
        (new SyncIsolationStatusJob($this->router))->handle();

        $this->assertSame(CustomerStatus::Active, $reopenedInForm->fresh()->status);
        $this->assertSame(CustomerStatus::Isolated, $isolatedInForm->fresh()->status);
    }

    public function test_router_failure_leaves_customers_untouched_and_fails_the_job(): void
    {
        $this->fakeRouterOs($this->router)->failWith = new MikrotikConnectionException('timeout');
        $customer = $this->customer('budi', CustomerStatus::Active);

        try {
            (new SyncIsolationStatusJob($this->router))->handle();
            $this->fail('Expected a connection exception.');
        } catch (MikrotikConnectionException) {
            // diharapkan
        }

        $this->assertSame(CustomerStatus::Active, $customer->fresh()->status);
    }
}
