<?php

namespace Tests\Feature\Vpn;

use App\Enums\VpnProtocol;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\Vpn\VpnScriptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VpnScriptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_script_for_sstp_points_to_server_host_and_port(): void
    {
        $server = VpnServer::factory()->create(['public_host' => 'vpn.example.com', 'sstp_port' => 4443]);
        $account = VpnAccount::factory()->for($server, 'server')->create(['protocol' => VpnProtocol::Sstp, 'username' => 'budi', 'password' => 'rahasia123']);

        $script = (new VpnScriptBuilder)->client($account);

        $this->assertStringContainsString('/interface sstp-client', $script);
        $this->assertStringContainsString('connect-to="vpn.example.com:4443"', $script);
        $this->assertStringContainsString('user="budi"', $script);
        $this->assertStringContainsString('password="rahasia123"', $script);
        $this->assertStringNotContainsString('l2tp-client', $script);
    }

    public function test_client_script_for_l2tp_includes_ipsec_secret(): void
    {
        $server = VpnServer::factory()->create(['public_host' => 'vpn.example.com', 'ipsec_secret' => 'ipsec-kunci-1']);
        $account = VpnAccount::factory()->for($server, 'server')->create(['protocol' => VpnProtocol::L2tp]);

        $script = (new VpnScriptBuilder)->client($account);

        $this->assertStringContainsString('/interface l2tp-client', $script);
        $this->assertStringContainsString('use-ipsec=yes', $script);
        $this->assertStringContainsString('ipsec-secret="ipsec-kunci-1"', $script);
    }

    public function test_special_characters_are_escaped_so_a_password_cannot_inject_commands(): void
    {
        $account = VpnAccount::factory()->create(['password' => 'a$b?c']);

        $script = (new VpnScriptBuilder)->client($account->load('server'));

        $this->assertStringContainsString('password="a\$b\?c"', $script);
    }

    public function test_server_account_script_creates_secret_and_port_forwards(): void
    {
        $account = VpnAccount::factory()->create([
            'username' => 'budi',
            'remote_address' => '10.99.0.5',
            'forwards' => [['name' => 'Winbox', 'public_port' => 20005, 'target_port' => 8291]],
        ]);

        $script = (new VpnScriptBuilder)->serverAccount($account);

        $this->assertStringContainsString('/ppp secret', $script);
        $this->assertStringContainsString('remote-address="10.99.0.5"', $script);
        $this->assertStringContainsString('profile="vpn-tunnel"', $script);
        $this->assertStringContainsString('dst-port="20005"', $script);
        $this->assertStringContainsString('to-addresses="10.99.0.5"', $script);
        $this->assertStringContainsString('to-ports="8291"', $script);
    }

    public function test_server_setup_only_enables_selected_protocols(): void
    {
        $server = VpnServer::factory()->create(['l2tp_enabled' => false, 'sstp_enabled' => true, 'sstp_port' => 443]);

        $script = (new VpnScriptBuilder)->serverSetup($server);

        $this->assertStringContainsString('/interface sstp-server server set enabled=yes port=443', $script);
        $this->assertStringNotContainsString('l2tp-server', $script);
        $this->assertStringNotContainsString('\\n', $script);
        $this->assertGreaterThan(5, substr_count($script, "\n"));
        $this->assertStringContainsString('ranges=10.99.0.2-10.99.0.254', $script);
    }

    public function test_l2tp_without_ipsec_omits_the_secret_everywhere(): void
    {
        $server = VpnServer::factory()->create(['use_ipsec' => false, 'ipsec_secret' => null, 'sstp_enabled' => false]);
        $account = VpnAccount::factory()->for($server, 'server')->create(['protocol' => VpnProtocol::L2tp]);
        $builder = new VpnScriptBuilder;

        $client = $builder->client($account);
        $setup = $builder->serverSetup($server);

        $this->assertStringContainsString('use-ipsec=no', $client);
        $this->assertStringNotContainsString('ipsec-secret', $client);
        $this->assertStringContainsString('use-ipsec="no"', $setup);
        $this->assertStringNotContainsString('ipsec-secret', $setup);
        $this->assertStringNotContainsString('ipsec-esp', $setup);
        $this->assertStringContainsString('dst-port="1701"', $setup);
    }
}
