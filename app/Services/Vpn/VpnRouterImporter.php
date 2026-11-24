<?php

namespace App\Services\Vpn;

use App\Enums\VpnAccountStatus;
use App\Enums\VpnProtocol;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\Mikrotik\RouterOsClientFactory;

/**
 * Mengambil akun VPN yang sudah berjalan di router server (PPP secret + rule dst-nat) menjadi akun di aplikasi,
 * tanpa mengubah apa pun di router: profile, IP tunnel, dan port forward lama dipertahankan apa adanya.
 */
class VpnRouterImporter
{
    public function __construct(private readonly RouterOsClientFactory $factory) {}

    /**
     * Daftar PPP secret di router beserta port forward-nya dan apakah bisa diimpor.
     *
     * @return list<array{username: string, name: string, service: string, profile: string, remote_address: string|null, disabled: bool, forwards: list<array{name: string, public_port: int, target_port: int}>, skipped_rules: int, status: string, reason: string|null}>
     */
    public function preview(VpnServer $server): array
    {
        $secrets = $this->readSecrets($server);
        $nat = $this->readNat($server);
        $imported = VpnAccount::query()->where('vpn_server_id', $server->id)->pluck('username')->all();
        $takenAddresses = VpnAccount::query()->where('vpn_server_id', $server->id)->pluck('remote_address')->all();
        $takenPorts = VpnAccount::query()->where('vpn_server_id', $server->id)->pluck('forwards')->flatten(1)->pluck('public_port')->map(fn (mixed $port): int => (int) $port)->all();

        return collect($secrets)->map(function (array $secret) use ($nat, $imported, $takenAddresses, $takenPorts): array {
            $address = ($secret['remote-address'] ?? '') !== '' ? $secret['remote-address'] : null;
            $username = (string) ($secret['name'] ?? '');
            [$forwards, $skipped] = $address !== null ? $this->forwardsFor($nat, $address, $takenPorts) : [[], 0];

            return [
                'username' => $username,
                'name' => $this->displayName($secret),
                'service' => $secret['service'] ?? 'any',
                'profile' => $secret['profile'] ?? 'default',
                'remote_address' => $address,
                'disabled' => ($secret['disabled'] ?? 'false') === 'true',
                'forwards' => $forwards,
                'skipped_rules' => $skipped,
                ...$this->verdict($username, $address, $imported, $takenAddresses),
            ];
        })->values()->all();
    }

    /**
     * Impor $usernames. Data dibaca ulang dari router (bukan dari input pengguna), jadi password dan IP tidak bisa dipalsukan.
     *
     * @param  list<string>  $usernames
     * @return int Jumlah akun yang dibuat.
     */
    public function import(VpnServer $server, array $usernames, int $monthlyPrice, ?string $expiresAt): int
    {
        $passwords = collect($this->readSecrets($server))->pluck('password', 'name');
        $created = 0;

        foreach ($this->preview($server) as $row) {
            if (! in_array($row['username'], $usernames, true) || $row['status'] !== 'importable') {
                continue;
            }

            VpnAccount::create([
                'vpn_server_id' => $server->id,
                'name' => $row['name'],
                'protocol' => $this->protocolFor($server, $row['service']),
                'username' => $row['username'],
                'password' => (string) ($passwords[$row['username']] ?? ''),
                'remote_address' => $row['remote_address'],
                'forwards' => $row['forwards'] ?: null,
                'status' => $row['disabled'] ? VpnAccountStatus::Disabled : VpnAccountStatus::Active,
                'adopted' => true,
                'expires_at' => $expiresAt,
                'monthly_price' => $monthlyPrice,
                'synced_at' => now(),
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @return list<array<string, string>>
     */
    private function readSecrets(VpnServer $server): array
    {
        return $this->factory->make($server->router)->command('/ppp/secret/print');
    }

    /**
     * @return list<array<string, string>>
     */
    private function readNat(VpnServer $server): array
    {
        return $this->factory->make($server->router)->command('/ip/firewall/nat/print', where: ['chain' => 'dstnat']);
    }

    /**
     * Rule tcp dst-nat ke $address dengan port tunggal; rule berentang (mis. 3000-3010) tidak bisa dikelola
     * dan dilewati. Port publik yang sudah dipakai akun lain atau dobel juga dilewati.
     *
     * @param  list<array<string, string>>  $nat
     * @param  list<int>  $takenPorts
     * @return array{0: list<array{name: string, public_port: int, target_port: int}>, 1: int}
     */
    private function forwardsFor(array $nat, string $address, array $takenPorts): array
    {
        $forwards = [];
        $skipped = 0;

        foreach ($nat as $rule) {
            $disabled = ($rule['disabled'] ?? 'false') === 'true';

            if (($rule['to-addresses'] ?? '') !== $address || ($rule['action'] ?? '') !== 'dst-nat' || $disabled) {
                continue;
            }

            $public = $rule['dst-port'] ?? '';
            $target = $rule['to-ports'] ?? '';

            if (! ctype_digit($public) || ! ctype_digit($target) || ($rule['protocol'] ?? 'tcp') !== 'tcp') {
                $skipped++;

                continue;
            }

            if (in_array((int) $public, $takenPorts, true) || collect($forwards)->contains('public_port', (int) $public)) {
                continue;
            }

            $forwards[] = ['name' => $this->forwardName($rule['comment'] ?? '', (int) $target), 'public_port' => (int) $public, 'target_port' => (int) $target];
        }

        return [$forwards, $skipped];
    }

    /**
     * @param  list<string>  $imported
     * @param  list<string>  $takenAddresses
     * @return array{status: string, reason: string|null}
     */
    private function verdict(string $username, ?string $address, array $imported, array $takenAddresses): array
    {
        return match (true) {
            in_array($username, $imported, true) => ['status' => 'imported', 'reason' => 'Sudah ada di aplikasi'],
            $address === null => ['status' => 'skipped', 'reason' => 'Tanpa IP tetap (remote-address)'],
            in_array($address, $takenAddresses, true) => ['status' => 'skipped', 'reason' => 'IP sudah dipakai akun lain'],
            ! preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username) => ['status' => 'skipped', 'reason' => 'Username berisi spasi/karakter tidak didukung'],
            default => ['status' => 'importable', 'reason' => null],
        };
    }

    /**
     * Nama pelanggan dari komentar secret ("VPN Olip / gowa" → "VPN Olip / gowa"); bila kosong memakai username.
     *
     * @param  array<string, string>  $secret
     */
    private function displayName(array $secret): string
    {
        $comment = trim($secret['comment'] ?? '');

        return mb_substr($comment !== '' ? $comment : (string) ($secret['name'] ?? ''), 0, 255);
    }

    /**
     * "Port 8291 | AZEE" → "Port 8291"; komentar lain dipakai apa adanya (maksimal 40 karakter).
     */
    private function forwardName(string $comment, int $target): string
    {
        $label = trim(explode('|', $comment)[0]);

        return mb_substr($label !== '' ? $label : "Port {$target}", 0, 40);
    }

    private function protocolFor(VpnServer $server, string $service): VpnProtocol
    {
        $preferred = VpnProtocol::tryFrom($service);

        if ($preferred !== null && $server->supports($preferred)) {
            return $preferred;
        }

        return $server->l2tp_enabled ? VpnProtocol::L2tp : VpnProtocol::Sstp;
    }
}
