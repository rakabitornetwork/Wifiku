<?php

namespace Tests\Feature\Vpn;

use App\Enums\VpnAccountStatus;
use App\Jobs\Vpn\ExpireVpnAccountsJob;
use App\Jobs\Vpn\RemoveVpnAccountJob;
use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Vpn\VpnProvisioner;
use App\Services\Whatsapp\VpnNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class VpnProvisionerTest extends TestCase
{
    use FakesRouterOs;
    use RefreshDatabase;

    public function test_sync_creates_secret_and_forward_and_is_idempotent(): void
    {
        $account = VpnAccount::factory()->create([
            'username' => 'budi',
            'remote_address' => '10.99.0.5',
            'forwards' => [['name' => 'Winbox', 'public_port' => 20005, 'target_port' => 8291]],
        ]);
        $fake = $this->fakeRouterOs($account->server->router);

        (new SyncVpnAccountJob($account->id, $account->server->router_id))->handle(app(VpnProvisioner::class));
        (new SyncVpnAccountJob($account->id, $account->server->router_id))->handle(app(VpnProvisioner::class));

        $secrets = $fake->rows('/ppp/secret');
        $this->assertCount(1, $secrets);
        $this->assertSame('10.99.0.5', $secrets[0]['remote-address']);
        $this->assertSame('vpn-tunnel', $secrets[0]['profile']);
        $this->assertSame('no', $secrets[0]['disabled']);
        $this->assertCount(1, $fake->rows('/ip/firewall/nat'));
        $this->assertNotNull($account->fresh()->synced_at);
    }

    public function test_sync_removes_forwards_that_are_no_longer_wanted(): void
    {
        $account = VpnAccount::factory()->create(['username' => 'budi', 'forwards' => [['name' => 'Winbox', 'public_port' => 20005, 'target_port' => 8291]]]);
        $fake = $this->fakeRouterOs($account->server->router);
        $provisioner = app(VpnProvisioner::class);
        $provisioner->syncAccount($account->load('server.router'));

        $account->update(['forwards' => null]);
        $provisioner->syncAccount($account->fresh()->load('server.router'));

        $this->assertCount(0, $fake->rows('/ip/firewall/nat'));
    }

    public function test_disabled_account_is_disabled_on_router_and_session_is_kicked(): void
    {
        $account = VpnAccount::factory()->create(['username' => 'budi', 'status' => VpnAccountStatus::Disabled]);
        $fake = $this->fakeRouterOs($account->server->router);
        $fake->seed('/ppp/active', ['name' => 'budi']);

        app(VpnProvisioner::class)->syncAccount($account->load('server.router'));

        $this->assertSame('yes', $fake->rows('/ppp/secret')[0]['disabled']);
        $this->assertCount(0, $fake->rows('/ppp/active'));
    }

    public function test_renaming_username_removes_the_old_secret(): void
    {
        $account = VpnAccount::factory()->create(['username' => 'lama']);
        $fake = $this->fakeRouterOs($account->server->router);
        $provisioner = app(VpnProvisioner::class);
        $provisioner->syncAccount($account->load('server.router'));

        $account->update(['username' => 'baru']);
        $provisioner->syncAccount($account->fresh()->load('server.router'), 'lama');

        $this->assertSame(['baru'], array_column($fake->rows('/ppp/secret'), 'name'));
    }

    public function test_remove_job_deletes_secret_and_forwards(): void
    {
        $account = VpnAccount::factory()->create(['username' => 'budi', 'forwards' => [['name' => 'Winbox', 'public_port' => 20005, 'target_port' => 8291]]]);
        $fake = $this->fakeRouterOs($account->server->router);
        app(VpnProvisioner::class)->syncAccount($account->load('server.router'));

        (new RemoveVpnAccountJob($account->vpn_server_id, $account->server->router_id, 'budi'))->handle(app(VpnProvisioner::class));

        $this->assertCount(0, $fake->rows('/ppp/secret'));
        $this->assertCount(0, $fake->rows('/ip/firewall/nat'));
    }

    public function test_failed_sync_records_the_error_and_rethrows_for_retry(): void
    {
        $account = VpnAccount::factory()->create();
        $this->fakeRouterOs($account->server->router)->failWith = new MikrotikConnectionException('Connection refused');

        try {
            (new SyncVpnAccountJob($account->id, $account->server->router_id))->handle(app(VpnProvisioner::class));
            $this->fail('Exception expected');
        } catch (MikrotikConnectionException) {
            $this->assertSame('Connection refused', $account->fresh()->last_error);
        }
    }

    public function test_setup_server_creates_pool_profile_servers_and_firewall(): void
    {
        $server = VpnServer::factory()->create();
        $fake = $this->fakeRouterOs($server->router);

        app(VpnProvisioner::class)->setupServer($server->load('router'));
        app(VpnProvisioner::class)->setupServer($server->fresh()->load('router'));

        $this->assertSame('10.99.0.2-10.99.0.254', $fake->rows('/ip/pool')[0]['ranges']);
        $this->assertCount(1, $fake->rows('/ppp/profile'));
        $this->assertSame('required', $fake->rows('/interface/l2tp-server/server')[0]['use-ipsec']);
        $this->assertSame('yes', $fake->rows('/interface/sstp-server/server')[0]['enabled']);
        $this->assertCount(3, $fake->rows('/ip/firewall/filter'));
        $this->assertCount(1, $fake->rows('/ip/firewall/nat'));
        // RouterOS menolak connection-nat-state di menu NAT; masquerade hanya boleh memakai kriteria yang valid di sana.
        $this->assertArrayNotHasKey('connection-nat-state', $fake->rows('/ip/firewall/nat')[0]);
        $this->assertNotNull($server->fresh()->setup_at);
    }

    public function test_expire_job_disables_overdue_active_accounts_and_queues_sync(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $overdue = VpnAccount::factory()->create(['expires_at' => now()->subDay()->toDateString()]);
        $current = VpnAccount::factory()->create(['expires_at' => now()->addDay()->toDateString()]);
        $forever = VpnAccount::factory()->create(['expires_at' => null]);

        (new ExpireVpnAccountsJob)->handle(app(VpnNotifier::class));

        $this->assertSame(VpnAccountStatus::Disabled, $overdue->fresh()->status);
        $this->assertSame(VpnAccountStatus::Active, $current->fresh()->status);
        $this->assertSame(VpnAccountStatus::Active, $forever->fresh()->status);
        Queue::assertPushed(SyncVpnAccountJob::class, 1);
    }

    public function test_setup_server_without_ipsec_configures_plain_l2tp(): void
    {
        $server = VpnServer::factory()->create(['use_ipsec' => false, 'ipsec_secret' => null, 'sstp_enabled' => false]);
        $fake = $this->fakeRouterOs($server->router);

        app(VpnProvisioner::class)->setupServer($server->load('router'));

        $l2tp = $fake->rows('/interface/l2tp-server/server')[0];
        $this->assertSame('no', $l2tp['use-ipsec']);
        $this->assertArrayNotHasKey('ipsec-secret', $l2tp);
        $this->assertSame(['1701'], array_column($fake->rows('/ip/firewall/filter'), 'dst-port'));
    }
}
