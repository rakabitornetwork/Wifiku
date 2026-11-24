<?php

namespace Tests\Feature\Vpn;

use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Vpn\VpnPortPlanner;
use App\Services\Vpn\VpnProvisioner;
use App\Services\Vpn\VpnRouterImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class VpnForwardPatternTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    public function test_planner_follows_the_server_pattern(): void
    {
        $server = VpnServer::factory()->make(['port_block_offset' => 122]);
        $planner = new VpnPortPlanner;

        // Pola nyata di router: .16 → 138xx, .17 → 139xx.
        $this->assertSame(13822, $planner->publicPort($server, '192.168.172.16', 22));
        $this->assertSame(13880, $planner->publicPort($server, '192.168.172.16', 80));
        $this->assertSame(13828, $planner->publicPort($server, '192.168.172.16', 8728));
        $this->assertSame(13891, $planner->publicPort($server, '192.168.172.16', 8291));
        $this->assertSame(13987, $planner->publicPort($server, '192.168.172.17', 8070));
        $this->assertNull($planner->publicPort($server, '192.168.172.16', 3389));
    }

    public function test_blank_public_ports_are_generated_from_the_assigned_ip(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $server = VpnServer::factory()->create(['pool_start' => '192.168.127.2', 'pool_end' => '192.168.127.254', 'local_address' => '192.168.127.1']);

        $this->actingAs($this->admin())->post('/vpn/accounts', [
            'vpn_server_id' => $server->id, 'name' => 'Budi', 'protocol' => 'sstp', 'username' => 'budi', 'password' => '', 'remote_address' => '', 'status' => 'active', 'monthly_price' => 0,
            'forwards' => [
                ['name' => 'Winbox', 'public_port' => '', 'target_port' => 8291],
                ['name' => 'API RouterOS', 'public_port' => '', 'target_port' => 8728],
            ],
        ])->assertSessionDoesntHaveErrors();

        $account = VpnAccount::sole();
        $this->assertSame('192.168.127.2', $account->remote_address);
        $this->assertSame([12491, 12428], array_column($account->forwards, 'public_port'));
    }

    public function test_non_standard_port_requires_a_manual_public_port(): void
    {
        $server = VpnServer::factory()->create();

        $this->actingAs($this->admin())->post('/vpn/accounts', [
            'vpn_server_id' => $server->id, 'name' => 'Budi', 'protocol' => 'sstp', 'username' => 'budi', 'status' => 'active', 'monthly_price' => 0,
            'forwards' => [['name' => 'RDP', 'public_port' => '', 'target_port' => 3389]],
        ])->assertSessionHasErrors('forwards.0.public_port');
    }

    public function test_generated_port_that_is_already_used_by_another_account_is_rejected(): void
    {
        $server = VpnServer::factory()->create();
        VpnAccount::factory()->for($server, 'server')->create(['remote_address' => '10.99.0.50', 'forwards' => [['name' => 'Winbox', 'public_port' => 17291, 'target_port' => 8291]]]);

        // .50 → (122 + 50) * 100 + 91 = 17291; akun baru dipaksa ke IP yang sama polanya tidak mungkin, jadi uji lewat port manual yang sama.
        $this->actingAs($this->admin())->post('/vpn/accounts', [
            'vpn_server_id' => $server->id, 'name' => 'Budi', 'protocol' => 'sstp', 'username' => 'budi', 'status' => 'active', 'monthly_price' => 0,
            'forwards' => [['name' => 'Winbox', 'public_port' => 17291, 'target_port' => 8291]],
        ])->assertSessionHasErrors('forwards.0.public_port');
    }

    public function test_sync_adopts_an_existing_rule_with_the_same_port_instead_of_duplicating(): void
    {
        $account = VpnAccount::factory()->create(['username' => 'azee', 'remote_address' => '192.168.172.12', 'forwards' => [['name' => 'Winbox', 'public_port' => 4391, 'target_port' => 8291]]]);
        $fake = $this->fakeRouterOs($account->server->router);
        $fake->seed('/ip/firewall/nat', ['chain' => 'dstnat', 'action' => 'dst-nat', 'protocol' => 'tcp', 'dst-port' => '4391', 'to-addresses' => '192.168.172.12', 'to-ports' => '8291', 'comment' => 'Port 8291 Winbox | AZEE']);

        app(VpnProvisioner::class)->syncAccount($account->load('server.router'));

        $rows = $fake->rows('/ip/firewall/nat');
        $this->assertCount(1, $rows);
        $this->assertSame('Port 8291 Winbox | AZEE', $rows[0]['comment']);
    }

    public function test_sync_refuses_to_take_over_a_port_used_by_another_target(): void
    {
        $account = VpnAccount::factory()->create(['remote_address' => '10.99.0.5', 'forwards' => [['name' => 'Winbox', 'public_port' => 3391, 'target_port' => 8291]]]);
        $fake = $this->fakeRouterOs($account->server->router);
        $fake->seed('/ip/firewall/nat', ['chain' => 'dstnat', 'action' => 'dst-nat', 'dst-port' => '3391', 'to-addresses' => '192.168.172.9', 'to-ports' => '8291', 'comment' => 'Port 8291 | Angel']);

        $this->expectException(MikrotikCommandException::class);

        app(VpnProvisioner::class)->syncAccount($account->load('server.router'));
    }

    public function test_adopted_account_keeps_its_profile_and_ip_on_the_router(): void
    {
        $account = VpnAccount::factory()->create(['username' => 'azee', 'password' => 'baru-123', 'adopted' => true, 'remote_address' => '192.168.172.12']);
        $fake = $this->fakeRouterOs($account->server->router);
        $fake->seed('/ppp/secret', ['name' => 'azee', 'password' => 'lama', 'profile' => 'default-encryption', 'remote-address' => '192.168.172.12', 'local-address' => '192.168.172.254', 'service' => 'l2tp']);

        app(VpnProvisioner::class)->syncAccount($account->load('server.router'));

        $secret = $fake->rows('/ppp/secret')[0];
        $this->assertSame('baru-123', $secret['password']);
        $this->assertSame('default-encryption', $secret['profile']);
        $this->assertSame('192.168.172.254', $secret['local-address']);
    }

    public function test_import_preview_and_import_map_secrets_and_rules_without_touching_the_router(): void
    {
        $server = VpnServer::factory()->create(['sstp_enabled' => false]);
        $fake = $this->fakeRouterOs($server->router);
        $fake->seed('/ppp/secret', ['name' => 'azee', 'password' => 'rahasia-azee', 'service' => 'l2tp', 'profile' => 'default-encryption', 'remote-address' => '192.168.172.12', 'comment' => 'AZEE']);
        $fake->seed('/ppp/secret', ['name' => 'Olip', 'password' => 'x', 'service' => 'l2tp', 'profile' => 'l2tp-profile']);
        $fake->seed('/ppp/secret', ['name' => '3R SOLUSI MEDIA', 'password' => 'x', 'service' => 'l2tp', 'remote-address' => '192.168.172.10']);
        $fake->seed('/ip/firewall/nat', ['chain' => 'dstnat', 'action' => 'dst-nat', 'protocol' => 'tcp', 'dst-port' => '4391', 'to-addresses' => '192.168.172.12', 'to-ports' => '8291', 'comment' => 'Port 8291 Winbox | AZEE']);
        $fake->seed('/ip/firewall/nat', ['chain' => 'dstnat', 'action' => 'dst-nat', 'protocol' => 'tcp', 'dst-port' => '3000-3010', 'to-addresses' => '192.168.172.12', 'to-ports' => '3000-3010', 'comment' => 'range']);
        $writesBefore = collect($fake->paths())->filter(fn (string $path): bool => ! str_ends_with($path, '/print'))->count();

        $rows = collect(app(VpnRouterImporter::class)->preview($server->load('router')))->keyBy('username');

        $this->assertSame('importable', $rows['azee']['status']);
        $this->assertSame([['name' => 'Port 8291 Winbox', 'public_port' => 4391, 'target_port' => 8291]], $rows['azee']['forwards']);
        $this->assertSame(1, $rows['azee']['skipped_rules']);
        $this->assertSame('skipped', $rows['Olip']['status']);
        $this->assertSame('skipped', $rows['3R SOLUSI MEDIA']['status']);
        $this->assertArrayNotHasKey('password', $rows['azee']);

        $this->actingAs($this->admin())->post("/vpn/servers/{$server->id}/import", ['usernames' => ['azee', 'Olip'], 'monthly_price' => 50000, 'expires_at' => '2027-01-01'])->assertSessionDoesntHaveErrors();

        $account = VpnAccount::sole();
        $this->assertSame('azee', $account->username);
        $this->assertSame('rahasia-azee', $account->password);
        $this->assertTrue($account->adopted);
        $this->assertSame('192.168.172.12', $account->remote_address);
        $this->assertSame(50000, $account->monthly_price);
        $this->assertSame($writesBefore, collect($fake->paths())->filter(fn (string $path): bool => ! str_ends_with($path, '/print'))->count());

        $again = collect(app(VpnRouterImporter::class)->preview($server->fresh()->load('router')))->keyBy('username');
        $this->assertSame('imported', $again['azee']['status']);
    }

    public function test_adopted_account_outside_the_pool_can_still_be_edited(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $account = VpnAccount::factory()->create(['adopted' => true, 'remote_address' => '192.168.172.12', 'username' => 'azee']);

        $this->actingAs($this->admin())->put("/vpn/accounts/{$account->id}", [
            'vpn_server_id' => $account->vpn_server_id, 'name' => 'AZEE', 'protocol' => 'sstp', 'username' => 'azee', 'remote_address' => '192.168.172.12', 'status' => 'active', 'monthly_price' => 25000,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(25000, $account->fresh()->monthly_price);
    }
}
