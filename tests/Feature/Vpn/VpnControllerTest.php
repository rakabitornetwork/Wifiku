<?php

namespace Tests\Feature\Vpn;

use App\Jobs\Vpn\RemoveVpnAccountJob;
use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\Router;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class VpnControllerTest extends TestCase
{
    use CreatesUsers;
    use FakesRouterOs;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(VpnServer $server, array $overrides = []): array
    {
        return [
            'vpn_server_id' => $server->id,
            'name' => 'Budi',
            'whatsapp' => '0812345678901',
            'protocol' => 'sstp',
            'username' => 'budi',
            'password' => '',
            'remote_address' => '',
            'forwards' => [],
            'status' => 'active',
            'expires_at' => '',
            'monthly_price' => 25000,
            ...$overrides,
        ];
    }

    public function test_teknisi_cannot_open_vpn_pages(): void
    {
        $this->actingAs($this->userWithRole('Teknisi'))->get('/vpn')->assertForbidden();
    }

    public function test_admin_sees_accounts_and_servers_without_secrets(): void
    {
        VpnAccount::factory()->create();

        $this->actingAs($this->admin())->get('/vpn')->assertInertia(
            fn (Assert $page) => $page->component('Vpn/Index')
                ->has('accounts.data', 1)
                ->has('servers', 1)
                ->missing('accounts.data.0.password')
                ->missing('servers.0.ipsec_secret'),
        );
    }

    public function test_creating_an_account_autofills_password_and_next_free_ip_and_queues_sync(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $server = VpnServer::factory()->create();
        VpnAccount::factory()->for($server, 'server')->create(['remote_address' => '10.99.0.2']);

        $this->actingAs($this->admin())->post('/vpn/accounts', $this->accountPayload($server))->assertSessionDoesntHaveErrors();

        $account = VpnAccount::where('username', 'budi')->firstOrFail();
        $this->assertSame('10.99.0.3', $account->remote_address);
        $this->assertGreaterThanOrEqual(14, strlen($account->password));
        $this->assertSame('628123456789'.'01', $account->whatsapp);
        Queue::assertPushed(SyncVpnAccountJob::class);
    }

    public function test_remote_address_must_be_inside_the_pool_and_unique(): void
    {
        $server = VpnServer::factory()->create();
        VpnAccount::factory()->for($server, 'server')->create(['remote_address' => '10.99.0.9']);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/vpn/accounts', $this->accountPayload($server, ['remote_address' => '192.168.1.5']))->assertSessionHasErrors('remote_address');
        $this->actingAs($admin)->post('/vpn/accounts', $this->accountPayload($server, ['remote_address' => '10.99.0.9']))->assertSessionHasErrors('remote_address');
        $this->actingAs($admin)->post('/vpn/accounts', $this->accountPayload($server, ['remote_address' => '10.99.0.1']))->assertSessionHasErrors('remote_address');
    }

    public function test_protocol_must_be_enabled_on_the_server(): void
    {
        $server = VpnServer::factory()->create(['l2tp_enabled' => false]);

        $this->actingAs($this->admin())->post('/vpn/accounts', $this->accountPayload($server, ['protocol' => 'l2tp']))->assertSessionHasErrors('protocol');
    }

    public function test_public_forward_ports_are_unique_per_server_and_exclude_reserved_ports(): void
    {
        $server = VpnServer::factory()->create();
        VpnAccount::factory()->for($server, 'server')->create(['forwards' => [['name' => 'Winbox', 'public_port' => 20001, 'target_port' => 8291]]]);
        $admin = $this->admin();

        $taken = $this->accountPayload($server, ['forwards' => [['name' => 'Winbox', 'public_port' => 20001, 'target_port' => 8291]]]);
        $reserved = $this->accountPayload($server, ['forwards' => [['name' => 'Winbox', 'public_port' => 8291, 'target_port' => 8291]]]);

        $this->actingAs($admin)->post('/vpn/accounts', $taken)->assertSessionHasErrors('forwards.0.public_port');
        $this->actingAs($admin)->post('/vpn/accounts', $reserved)->assertSessionHasErrors('forwards.0.public_port');
    }

    public function test_update_keeps_password_when_blank_and_queues_sync_with_previous_username(): void
    {
        Queue::fake([SyncVpnAccountJob::class]);
        $account = VpnAccount::factory()->create(['username' => 'lama', 'password' => 'password-lama']);

        $payload = $this->accountPayload($account->server, ['username' => 'baru', 'remote_address' => $account->remote_address]);
        $this->actingAs($this->admin())->put("/vpn/accounts/{$account->id}", $payload)->assertSessionDoesntHaveErrors();

        $this->assertSame('password-lama', $account->fresh()->password);
        Queue::assertPushed(SyncVpnAccountJob::class, fn (SyncVpnAccountJob $job): bool => $job->previousUsername === 'lama');
    }

    public function test_deleting_an_account_queues_router_cleanup(): void
    {
        Queue::fake([RemoveVpnAccountJob::class]);
        $account = VpnAccount::factory()->create(['username' => 'budi']);

        $this->actingAs($this->admin())->delete("/vpn/accounts/{$account->id}")->assertRedirect();

        $this->assertModelMissing($account);
        Queue::assertPushed(RemoveVpnAccountJob::class, fn (RemoveVpnAccountJob $job): bool => $job->username === 'budi');
    }

    public function test_scripts_endpoint_returns_client_and_server_scripts(): void
    {
        $account = VpnAccount::factory()->create(['username' => 'budi']);

        $this->actingAs($this->admin())->getJson("/vpn/accounts/{$account->id}/scripts")
            ->assertOk()
            ->assertJsonPath('client', fn (string $script): bool => str_contains($script, 'sstp-client'))
            ->assertJsonPath('server', fn (string $script): bool => str_contains($script, '/ppp secret'));
    }

    public function test_server_requires_ipsec_secret_for_l2tp_and_a_valid_pool(): void
    {
        $router = Router::factory()->create();
        $admin = $this->admin();
        $base = ['router_id' => $router->id, 'name' => 'VPN', 'public_host' => 'vpn.example.com', 'local_address' => '10.99.0.1', 'pool_start' => '10.99.0.2', 'pool_end' => '10.99.0.50', 'ipsec_secret' => 'kunci-ipsec-1', 'sstp_port' => 443, 'use_ipsec' => true, 'port_block_offset' => 122, 'l2tp_enabled' => true, 'sstp_enabled' => true, 'is_active' => true];

        $this->actingAs($admin)->post('/vpn/servers', [...$base, 'ipsec_secret' => ''])->assertSessionHasErrors('ipsec_secret');
        $this->actingAs($admin)->post('/vpn/servers', [...$base, 'pool_start' => '10.99.0.60'])->assertSessionHasErrors('pool_end');
        $this->actingAs($admin)->post('/vpn/servers', [...$base, 'local_address' => '10.99.0.10'])->assertSessionHasErrors('local_address');
        $this->actingAs($admin)->post('/vpn/servers', [...$base, 'ipsec_secret' => '', 'use_ipsec' => false])->assertSessionDoesntHaveErrors();
        $this->assertNull(VpnServer::firstOrFail()->ipsec_secret);
        VpnServer::query()->delete();

        $this->actingAs($admin)->post('/vpn/servers', $base)->assertSessionDoesntHaveErrors();

        $this->assertSame('kunci-ipsec-1', VpnServer::firstOrFail()->ipsec_secret);
    }

    public function test_install_pushes_setup_to_the_router(): void
    {
        $server = VpnServer::factory()->create();
        $fake = $this->fakeRouterOs($server->router);

        $this->actingAs($this->admin())->post("/vpn/servers/{$server->id}/install")->assertRedirect();

        $this->assertCount(1, $fake->rows('/ip/pool'));
        $this->assertNotNull($server->fresh()->setup_at);
    }

    public function test_server_with_accounts_cannot_be_deleted(): void
    {
        $account = VpnAccount::factory()->create();

        $this->actingAs($this->admin())->delete("/vpn/servers/{$account->vpn_server_id}")->assertRedirect();

        $this->assertModelExists($account->server);
    }
}
