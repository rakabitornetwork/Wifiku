<?php

namespace Tests\Feature\Services\Mikrotik;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\HotspotProfile;
use App\Models\Package;
use App\Models\Router;
use App\Models\Voucher;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class MikrotikServiceTest extends TestCase
{
    use FakesRouterOs;
    use RefreshDatabase;

    private function customerOn(Router $router, array $attributes = []): Customer
    {
        return Customer::factory()
            ->for($router)
            ->for(Package::factory()->create(['mikrotik_profile' => 'HOME-20']))
            ->create(['pppoe_username' => 'budi', 'pppoe_password' => 'rahasia', ...$attributes]);
    }

    public function test_sync_creates_missing_secret_with_package_profile(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $customer = $this->customerOn($router);

        MikrotikService::forRouter($router)->syncPppoeSecret($customer);

        $secret = $fake->rows('/ppp/secret')[0];
        $this->assertSame('budi', $secret['name']);
        $this->assertSame('rahasia', $secret['password']);
        $this->assertSame('HOME-20', $secret['profile']);
        $this->assertSame('pppoe', $secret['service']);
        $this->assertSame('no', $secret['disabled']);
        $this->assertStringContainsString($customer->customer_code, $secret['comment']);
    }

    public function test_sync_uses_isolir_profile_for_isolated_customer_and_kicks_session(): void
    {
        $router = Router::factory()->create(['isolir_profile' => 'ISOLIR']);
        $fake = $this->fakeRouterOs($router);
        $customer = $this->customerOn($router, ['status' => CustomerStatus::Isolated]);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => 'HOME-20']);
        $fake->seed('/ppp/active', ['name' => 'budi']);

        MikrotikService::forRouter($router)->syncPppoeSecret($customer);

        $this->assertCount(1, $fake->rows('/ppp/secret'));
        $this->assertSame('ISOLIR', $fake->rows('/ppp/secret')[0]['profile']);
        $this->assertSame([], $fake->rows('/ppp/active'));
    }

    public function test_sync_does_not_kick_session_when_nothing_relevant_changed(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $customer = $this->customerOn($router);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => 'HOME-20']);
        $fake->seed('/ppp/active', ['name' => 'budi']);

        MikrotikService::forRouter($router)->syncPppoeSecret($customer);

        $this->assertCount(1, $fake->rows('/ppp/active'));
    }

    public function test_sync_renames_secret_when_username_changed(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $customer = $this->customerOn($router, ['pppoe_username' => 'budi.baru']);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => 'HOME-20']);

        MikrotikService::forRouter($router)->syncPppoeSecret($customer, previousUsername: 'budi');

        $this->assertSame(['budi.baru'], array_column($fake->rows('/ppp/secret'), 'name'));
    }

    public function test_sync_disables_secret_of_inactive_customer(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $customer = $this->customerOn($router, ['status' => CustomerStatus::Inactive]);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => 'HOME-20', 'disabled' => 'no']);
        $fake->seed('/ppp/active', ['name' => 'budi']);

        MikrotikService::forRouter($router)->syncPppoeSecret($customer);

        $this->assertSame('yes', $fake->rows('/ppp/secret')[0]['disabled']);
        $this->assertSame([], $fake->rows('/ppp/active'));
    }

    public function test_set_profile_changes_profile_and_kicks_only_that_user(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $customer = $this->customerOn($router);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => 'HOME-20']);
        $fake->seed('/ppp/active', ['name' => 'budi']);
        $fake->seed('/ppp/active', ['name' => 'siti']);

        MikrotikService::forRouter($router)->setPppoeProfile($customer, 'ISOLIR');

        $this->assertSame('ISOLIR', $fake->rows('/ppp/secret')[0]['profile']);
        $this->assertSame(['siti'], array_column($fake->rows('/ppp/active'), 'name'));
    }

    public function test_set_profile_is_a_no_op_when_profile_already_applied(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => 'ISOLIR']);

        MikrotikService::forRouter($router)->setPppoeProfile($this->customerOn($router), 'ISOLIR');

        $this->assertNotContains('/ppp/secret/set', $fake->paths());
    }

    public function test_set_profile_creates_secret_when_missing(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);

        MikrotikService::forRouter($router)->setPppoeProfile($this->customerOn($router), 'ISOLIR');

        $this->assertSame('ISOLIR', $fake->rows('/ppp/secret')[0]['profile']);
    }

    public function test_remove_secret_deletes_secret_and_session(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $fake->seed('/ppp/secret', ['name' => 'budi']);
        $fake->seed('/ppp/active', ['name' => 'budi']);

        MikrotikService::forRouter($router)->removePppoeSecret('budi');

        $this->assertSame([], $fake->rows('/ppp/secret'));
        $this->assertSame([], $fake->rows('/ppp/active'));
    }

    public function test_creates_hotspot_user_with_profile_limits(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $profile = HotspotProfile::factory()->for($router)->create(['mikrotik_profile' => 'HS-1D', 'uptime_limit_minutes' => 120, 'data_limit_mb' => 2]);
        $voucher = Voucher::factory()->make(['code' => 'WF1234', 'password' => 'WF1234']);

        MikrotikService::forRouter($router)->createHotspotUser($voucher, $profile, 'vc-B1');

        $this->assertSame(
            ['name' => 'WF1234', 'password' => 'WF1234', 'profile' => 'HS-1D', 'comment' => 'vc-B1', 'limit-uptime' => '120m', 'limit-bytes-total' => (string) (2 * 1024 * 1024)],
            array_diff_key($fake->rows('/ip/hotspot/user')[0], ['.id' => true]),
        );
        $this->assertSame(['WF1234'], MikrotikService::forRouter($router)->hotspotUserNames('vc-B1'));
    }

    public function test_removes_hotspot_user_and_active_session(): void
    {
        $router = Router::factory()->create();
        $fake = $this->fakeRouterOs($router);
        $fake->seed('/ip/hotspot/user', ['name' => 'WF1234']);
        $fake->seed('/ip/hotspot/active', ['user' => 'WF1234']);

        MikrotikService::forRouter($router)->removeHotspotUser('WF1234');

        $this->assertSame([], $fake->rows('/ip/hotspot/user'));
        $this->assertSame([], $fake->rows('/ip/hotspot/active'));
    }
}
