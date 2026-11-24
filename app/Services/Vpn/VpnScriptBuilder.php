<?php

namespace App\Services\Vpn;

use App\Enums\VpnAccountStatus;
use App\Enums\VpnProtocol;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use Illuminate\Support\Str;

/**
 * Menyusun script RouterOS (.rsc) yang siap ditempel di terminal: untuk router server penyedia
 * dan untuk router pelanggan. Semua baris idempotent — aman dijalankan berulang kali.
 */
class VpnScriptBuilder
{
    public const PROFILE = 'vpn-tunnel';

    public const POOL = 'vpn-pool';

    public const RULE_PREFIX = 'wifiku-vpn';

    /**
     * Pengaturan sekali jalan di router server: pool, profile, server L2TP/SSTP, dan firewall.
     */
    public function serverSetup(VpnServer $server): string
    {
        $pool = "{$server->pool_start}-{$server->pool_end}";
        $lines = [
            "# Setup server VPN Tunnel: {$server->name}",
            '/ip pool',
            ':if ([:len [find name='.self::POOL.']] = 0) do={ add name='.self::POOL." ranges={$pool} } else={ set [find name=".self::POOL."] ranges={$pool} }",
            '/ppp profile',
            ':if ([:len [find name='.self::PROFILE.']] = 0) do={ add name='.self::PROFILE." local-address={$server->local_address} remote-address=".self::POOL.' use-encryption=yes change-tcp-mss=yes } else={ set [find name='.self::PROFILE."] local-address={$server->local_address} remote-address=".self::POOL.' }',
        ];

        if ($server->l2tp_enabled) {
            $lines[] = '/interface l2tp-server server set enabled=yes default-profile='.self::PROFILE.' authentication=mschap2 '.collect($this->l2tpServerAttributes($server))
                ->map(fn (string $value, string $key): string => $key.'='.$this->quote($value))
                ->implode(' ');
        }

        if ($server->sstp_enabled) {
            $lines[] = '/interface sstp-server server set enabled=yes port='.$server->sstp_port.' default-profile='.self::PROFILE.' authentication=mschap2';
        }

        $lines[] = '/ip firewall filter';

        foreach ($this->serverFirewallRules($server) as $rule) {
            $lines[] = $this->ensureRule('filter', $rule['comment'], $rule['attributes'], '0');
        }

        $lines[] = '/ip firewall nat';
        $lines[] = $this->ensureRule('nat', self::RULE_PREFIX.'-masquerade', $this->masqueradeAttributes($server));

        if ($server->sstp_enabled) {
            $lines[] = '# SSTP membutuhkan sertifikat TLS di server: /interface sstp-server server set certificate=<nama-sertifikat>';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Atribut L2TP server: dengan IPsec (secret dipakai bersama) atau L2TP polos.
     *
     * @return array<string, string>
     */
    public function l2tpServerAttributes(VpnServer $server): array
    {
        return $server->use_ipsec
            ? ['use-ipsec' => 'required', 'ipsec-secret' => (string) $server->ipsec_secret]
            : ['use-ipsec' => 'no'];
    }

    /**
     * Rule firewall input yang dibutuhkan server agar tunnel dapat tersambung.
     *
     * @return list<array{comment: string, attributes: array<string, string|int>}>
     */
    public function serverFirewallRules(VpnServer $server): array
    {
        $rules = [];

        if ($server->l2tp_enabled) {
            $rules[] = ['comment' => self::RULE_PREFIX.'-l2tp', 'attributes' => ['chain' => 'input', 'action' => 'accept', 'protocol' => 'udp', 'dst-port' => $server->use_ipsec ? '500,1701,4500' : '1701']];

            if ($server->use_ipsec) {
                $rules[] = ['comment' => self::RULE_PREFIX.'-ipsec-esp', 'attributes' => ['chain' => 'input', 'action' => 'accept', 'protocol' => 'ipsec-esp']];
            }
        }

        if ($server->sstp_enabled) {
            $rules[] = ['comment' => self::RULE_PREFIX.'-sstp', 'attributes' => ['chain' => 'input', 'action' => 'accept', 'protocol' => 'tcp', 'dst-port' => (string) $server->sstp_port]];
        }

        return $rules;
    }

    /**
     * Masquerade agar balasan port forward (dst-nat) kembali lewat tunnel.
     *
     * @return array<string, string>
     */
    public function masqueradeAttributes(VpnServer $server): array
    {
        return ['chain' => 'srcnat', 'action' => 'masquerade', 'dst-address' => "{$server->pool_start}-{$server->pool_end}"];
    }

    /**
     * Script di router server untuk satu akun: PPP secret dan port forward.
     */
    public function serverAccount(VpnAccount $account): string
    {
        $secret = $this->assignments($this->secretAttributes($account));

        $lines = [
            "# Akun VPN {$account->username} ({$account->name})",
            '/ppp secret',
            ':if ([:len [find name='.$this->quote($account->username).']] = 0) do={ add '.$secret.' } else={ set [find name='.$this->quote($account->username).'] '.$secret.' }',
        ];

        $forwards = $this->forwardRules($account);

        if ($forwards !== []) {
            $lines[] = '/ip firewall nat';

            foreach ($forwards as $rule) {
                $lines[] = $this->ensureRule('nat', $rule['comment'], $rule['attributes']);
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Script untuk menghapus akun dari router server.
     */
    public function serverAccountRemoval(VpnAccount $account): string
    {
        return implode("\n", [
            "# Hapus akun VPN {$account->username}",
            '/ppp active remove [find name='.$this->quote($account->username).']',
            '/ppp secret remove [find name='.$this->quote($account->username).']',
            '/ip firewall nat remove [find where comment~"^'.self::RULE_PREFIX.':'.$account->username.':"]',
        ])."\n";
    }

    /**
     * Script untuk router pelanggan: membuat interface tunnel ke server VPN.
     */
    public function client(VpnAccount $account): string
    {
        $server = $account->server;
        $name = $this->interfaceName($account);
        $quotedName = $this->quote($name);
        $comment = $this->quote(self::RULE_PREFIX);

        if ($account->protocol === VpnProtocol::L2tp) {
            $menu = '/interface l2tp-client';
            $attributes = [
                'connect-to' => $this->quote($server->public_host),
                'user' => $this->quote($account->username),
                'password' => $this->quote((string) $account->password),
                ...($server->use_ipsec
                    ? ['use-ipsec' => 'yes', 'ipsec-secret' => $this->quote((string) $server->ipsec_secret)]
                    : ['use-ipsec' => 'no']),
                'allow' => 'mschap2',
                'profile' => 'default-encryption',
                'add-default-route' => 'no',
                'disabled' => 'no',
            ];
        } else {
            $menu = '/interface sstp-client';
            $attributes = [
                'connect-to' => $this->quote("{$server->public_host}:{$server->sstp_port}"),
                'user' => $this->quote($account->username),
                'password' => $this->quote((string) $account->password),
                'authentication' => 'mschap2',
                'profile' => 'default-encryption',
                'verify-server-certificate' => 'no',
                'add-default-route' => 'no',
                'disabled' => 'no',
            ];
        }

        $values = collect($attributes)->map(fn (string $value, string $key): string => "{$key}={$value}")->implode(' ');

        return implode("\n", [
            "# Tunnel VPN ke {$server->name} — akun {$account->username}",
            $menu,
            ":if ([:len [find name={$quotedName}]] = 0) do={ add name={$quotedName} {$values} comment={$comment} } else={ set [find name={$quotedName}] {$values} }",
            '/ip firewall filter',
            ':if ([:len [find comment='.$this->quote(self::RULE_PREFIX.'-input').']] = 0) do={ add chain=input action=accept in-interface='.$quotedName.' comment='.$this->quote(self::RULE_PREFIX.'-input').' place-before=0 }',
            "# IP tunnel Anda: {$account->remote_address}",
        ])."\n";
    }

    /**
     * Atribut PPP secret untuk akun (dipakai script maupun push API).
     *
     * @return array<string, string>
     */
    public function secretAttributes(VpnAccount $account): array
    {
        return [
            'name' => $account->username,
            'password' => (string) $account->password,
            'service' => $account->protocol->value,
            'profile' => self::PROFILE,
            'remote-address' => $account->remote_address,
            'disabled' => $account->status === VpnAccountStatus::Active ? 'no' : 'yes',
            'comment' => self::RULE_PREFIX.': '.$account->name,
        ];
    }

    /**
     * Rule dst-nat untuk port forward akun, mis. Winbox pelanggan lewat port publik server.
     *
     * @return list<array{comment: string, attributes: array<string, string>}>
     */
    public function forwardRules(VpnAccount $account): array
    {
        $host = $account->server->public_host;

        return collect($account->forwards ?? [])->map(fn (array $forward): array => [
            'comment' => self::forwardComment($account->username, (int) $forward['public_port']),
            'attributes' => [
                'chain' => 'dstnat',
                'action' => 'dst-nat',
                'protocol' => 'tcp',
                // Seperti rule lama di server: bila alamat publik berupa IP, rule dibatasi ke IP itu.
                ...(filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? ['dst-address' => $host] : []),
                'dst-port' => (string) $forward['public_port'],
                'to-addresses' => $account->remote_address,
                'to-ports' => (string) $forward['target_port'],
            ],
        ])->all();
    }

    public static function forwardComment(string $username, int $publicPort): string
    {
        return self::RULE_PREFIX.":{$username}:{$publicPort}";
    }

    public function interfaceName(VpnAccount $account): string
    {
        return 'vpn-'.Str::of($account->username)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->limit(20, '');
    }

    /**
     * @param  array<string, string|int>  $attributes
     */
    private function ensureRule(string $menu, string $comment, array $attributes, ?string $placeBefore = null): string
    {
        $values = $this->assignments([...$attributes, 'comment' => $comment]);
        $position = $placeBefore !== null ? " place-before={$placeBefore}" : '';

        return ':if ([:len [find comment='.$this->quote($comment).']] = 0) do={ add '.$values.$position.' } else={ set [find comment='.$this->quote($comment).'] '.$this->assignments($attributes).' }';
    }

    /**
     * @param  array<string, string|int>  $attributes
     */
    private function assignments(array $attributes): string
    {
        return collect($attributes)->map(fn (string|int $value, string $key): string => $key.'='.$this->quote((string) $value))->implode(' ');
    }

    /**
     * Kutip string RouterOS dan escape karakter yang bermakna khusus ( \ " $ ? ).
     */
    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', '$', '?'], ['\\\\', '\\"', '\\$', '\\?'], $value).'"';
    }
}
