<?php

namespace App\Services\Vpn;

use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Mikrotik\RouterOsClient;
use App\Services\Mikrotik\RouterOsClientFactory;
use Illuminate\Support\Arr;

/**
 * Memasang konfigurasi VPN ke router server lewat API RouterOS: setup server, PPP secret, dan port forward akun.
 */
class VpnProvisioner
{
    public function __construct(private readonly RouterOsClientFactory $factory, private readonly VpnScriptBuilder $scripts) {}

    /**
     * Pasang pool, profile, server L2TP/SSTP, dan firewall di router server.
     *
     * @throws MikrotikConnectionException
     * @throws MikrotikCommandException
     */
    public function setupServer(VpnServer $server): void
    {
        $client = $this->factory->make($server->router);
        $pool = "{$server->pool_start}-{$server->pool_end}";

        $this->upsert($client, '/ip/pool', ['name' => VpnScriptBuilder::POOL], ['ranges' => $pool]);
        $this->upsert($client, '/ppp/profile', ['name' => VpnScriptBuilder::PROFILE], [
            'local-address' => $server->local_address,
            'remote-address' => VpnScriptBuilder::POOL,
            'use-encryption' => 'yes',
            'change-tcp-mss' => 'yes',
        ]);

        if ($server->l2tp_enabled) {
            $client->command('/interface/l2tp-server/server/set', [
                'enabled' => 'yes',
                'default-profile' => VpnScriptBuilder::PROFILE,
                'authentication' => 'mschap2',
                ...$this->scripts->l2tpServerAttributes($server),
            ]);
        }

        if ($server->sstp_enabled) {
            $client->command('/interface/sstp-server/server/set', [
                'enabled' => 'yes',
                'port' => $server->sstp_port,
                'default-profile' => VpnScriptBuilder::PROFILE,
                'authentication' => 'mschap2',
            ]);
        }

        foreach ($this->scripts->serverFirewallRules($server) as $rule) {
            $this->upsert($client, '/ip/firewall/filter', ['comment' => $rule['comment']], $rule['attributes'], ['place-before' => '0']);
        }

        $this->upsert($client, '/ip/firewall/nat', ['comment' => VpnScriptBuilder::RULE_PREFIX.'-masquerade'], $this->scripts->masqueradeAttributes($server));

        $server->forceFill(['setup_at' => now()])->save();
    }

    /**
     * Samakan PPP secret dan port forward akun di router server dengan data aplikasi.
     * $previousUsername dipakai saat username diganti agar secret lama tidak tertinggal.
     *
     * @throws MikrotikConnectionException
     * @throws MikrotikCommandException
     */
    public function syncAccount(VpnAccount $account, ?string $previousUsername = null): void
    {
        $server = $account->server;
        $client = $this->factory->make($server->router);

        if ($previousUsername !== null && $previousUsername !== $account->username) {
            $this->purge($client, $previousUsername);
        }

        $this->syncSecret($client, $account);
        $this->syncForwards($client, $account);

        if ($account->status->value !== 'active') {
            $this->kick($client, $account->username);
        }

        $account->forceFill(['synced_at' => now(), 'last_error' => null])->save();
    }

    /**
     * Hapus PPP secret, sesi aktif, dan port forward milik $username dari router server.
     *
     * @throws MikrotikConnectionException
     * @throws MikrotikCommandException
     */
    public function removeAccount(VpnServer $server, string $username): void
    {
        $this->purge($this->factory->make($server->router), $username);
    }

    private function purge(RouterOsClient $client, string $username): void
    {
        $this->kick($client, $username);

        foreach ($client->command('/ppp/secret/print', where: ['name' => $username]) as $row) {
            $client->command('/ppp/secret/remove', ['.id' => $row['.id']]);
        }

        foreach ($this->forwardRows($client, $username) as $row) {
            $client->command('/ip/firewall/nat/remove', ['.id' => $row['.id']]);
        }
    }

    private function kick(RouterOsClient $client, string $username): void
    {
        foreach ($client->command('/ppp/active/print', where: ['name' => $username]) as $session) {
            $client->command('/ppp/active/remove', ['.id' => $session['.id']]);
        }
    }

    /**
     * Akun hasil impor hanya disamakan password dan status aktifnya; profile dan IP lamanya dibiarkan.
     */
    private function syncSecret(RouterOsClient $client, VpnAccount $account): void
    {
        $attributes = $this->scripts->secretAttributes($account);
        $existing = $client->command('/ppp/secret/print', where: ['name' => $account->username])[0] ?? null;

        if ($existing === null) {
            $client->command('/ppp/secret/add', $attributes);

            return;
        }

        $client->command('/ppp/secret/set', ['.id' => $existing['.id'], ...($account->adopted ? Arr::only($attributes, ['password', 'disabled']) : $attributes)]);
    }

    /**
     * Samakan rule dst-nat akun dengan data aplikasi. Rule dikenali dari port publiknya, sehingga rule
     * lama yang sudah ada (mis. hasil impor) dipakai ulang, bukan digandakan. Rule milik orang lain
     * di port yang sama tidak ditimpa: sinkronisasi gagal dengan pesan yang jelas.
     */
    private function syncForwards(RouterOsClient $client, VpnAccount $account): void
    {
        $wanted = $this->scripts->forwardRules($account);
        $wantedPorts = array_map(fn (array $rule): string => $rule['attributes']['dst-port'], $wanted);
        $rows = $client->command('/ip/firewall/nat/print', where: ['chain' => 'dstnat']);

        foreach ($this->forwardRows($client, $account->username) as $row) {
            if (! in_array($row['dst-port'] ?? '', $wantedPorts, true)) {
                $client->command('/ip/firewall/nat/remove', ['.id' => $row['.id']]);
            }
        }

        foreach ($wanted as $rule) {
            $port = $rule['attributes']['dst-port'];
            $existing = collect($rows)->first(fn (array $row): bool => ($row['dst-port'] ?? null) === $port);

            if ($existing === null) {
                $client->command('/ip/firewall/nat/add', [...$rule['attributes'], 'comment' => $rule['comment']]);
            } elseif (($existing['to-addresses'] ?? '') !== $account->remote_address) {
                throw new MikrotikCommandException("Port publik {$port} sudah dipakai rule NAT lain ({$existing['to-addresses']}) di router server.");
            } elseif (str_starts_with($existing['comment'] ?? '', VpnScriptBuilder::RULE_PREFIX.':')) {
                $client->command('/ip/firewall/nat/set', ['.id' => $existing['.id'], ...$rule['attributes']]);
            } elseif (($existing['to-ports'] ?? '') !== $rule['attributes']['to-ports']) {
                $client->command('/ip/firewall/nat/set', ['.id' => $existing['.id'], 'to-ports' => $rule['attributes']['to-ports']]);
            }
        }
    }

    /**
     * Rule NAT forward milik satu username.
     *
     * @return list<array<string, string>>
     */
    private function forwardRows(RouterOsClient $client, string $username): array
    {
        $prefix = VpnScriptBuilder::RULE_PREFIX.":{$username}:";

        return array_values(array_filter(
            $client->command('/ip/firewall/nat/print'),
            fn (array $row): bool => str_starts_with($row['comment'] ?? '', $prefix),
        ));
    }

    /**
     * Ubah baris yang cocok dengan $where, atau buat baru (dengan $createOnly tambahan) bila belum ada.
     *
     * @param  array<string, string|int>  $where
     * @param  array<string, string|int>  $attributes
     * @param  array<string, string|int>  $createOnly
     */
    private function upsert(RouterOsClient $client, string $menu, array $where, array $attributes, array $createOnly = []): void
    {
        $existing = $client->command("{$menu}/print", where: $where)[0] ?? null;

        if ($existing === null) {
            $client->command("{$menu}/add", [...$where, ...$attributes, ...$createOnly]);

            return;
        }

        $client->command("{$menu}/set", ['.id' => $existing['.id'], ...$attributes]);
    }
}
