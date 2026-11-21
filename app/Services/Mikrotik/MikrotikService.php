<?php

namespace App\Services\Mikrotik;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\HotspotProfile;
use App\Models\Router;
use App\Models\Voucher;

/**
 * Operasi RouterOS untuk satu router (Multi-NAS). Satu instance memakai satu koneksi yang dibuka saat perintah pertama.
 */
class MikrotikService
{
    private ?RouterOsClient $client = null;

    public function __construct(protected Router $router, private readonly RouterOsClientFactory $factory) {}

    /**
     * Buat instance service untuk router tertentu.
     */
    public static function forRouter(Router $router): self
    {
        return new self($router, app(RouterOsClientFactory::class));
    }

    /**
     * Nama identity router; dipakai sebagai uji koneksi.
     */
    public function identity(): string
    {
        return $this->client()->command('/system/identity/print')[0]['name'] ?? '';
    }

    /**
     * Seluruh PPP secret di router (untuk impor pelanggan).
     *
     * @return list<array<string, string>>
     */
    public function pppSecrets(): array
    {
        return $this->client()->command('/ppp/secret/print');
    }

    /**
     * Profile PPP yang sedang dipakai tiap secret, dikunci per username.
     *
     * @return array<string, string>
     */
    public function secretProfiles(): array
    {
        $profiles = [];

        foreach ($this->pppSecrets() as $secret) {
            $profiles[$secret['name']] = $secret['profile'] ?? 'default';
        }

        return $profiles;
    }

    /**
     * Seluruh PPP profile di router.
     *
     * @return list<array<string, string>>
     */
    public function pppProfiles(): array
    {
        return $this->client()->command('/ppp/profile/print');
    }

    /**
     * Profile PPP yang seharusnya aktif untuk pelanggan sesuai status layanannya.
     */
    public function profileFor(Customer $customer): string
    {
        return $customer->status === CustomerStatus::Isolated
            ? $this->router->isolir_profile
            : $customer->package->mikrotik_profile;
    }

    /**
     * Buat atau perbarui PPP secret agar sama dengan data pelanggan (nama, password, profile, disabled).
     * Bila username berubah, secret lama diganti namanya alih-alih membuat duplikat.
     */
    public function syncPppoeSecret(Customer $customer, ?string $previousUsername = null): void
    {
        $disabled = $customer->trashed() || $customer->status === CustomerStatus::Inactive;
        $profile = $this->profileFor($customer);

        $secret = $this->findPppoeSecret($customer->pppoe_username);

        if ($secret === null && $previousUsername !== null && $previousUsername !== $customer->pppoe_username) {
            $secret = $this->findPppoeSecret($previousUsername);
        }

        $attributes = [
            'name' => $customer->pppoe_username,
            'password' => $customer->pppoe_password,
            'profile' => $profile,
            'comment' => "{$customer->customer_code} {$customer->name}",
            'disabled' => $disabled ? 'yes' : 'no',
        ];

        if ($secret === null) {
            $this->client()->command('/ppp/secret/add', [...$attributes, 'service' => 'pppoe']);

            return;
        }

        $this->client()->command('/ppp/secret/set', ['.id' => $secret['.id'], ...$attributes]);

        if ($disabled || ($secret['profile'] ?? null) !== $profile || ($secret['name'] ?? null) !== $customer->pppoe_username) {
            $this->kickActiveSession($customer, $secret['name'] ?? null);
        }
    }

    /**
     * Pindahkan pelanggan ke profile tertentu dan putus sesi aktif agar profile baru langsung berlaku.
     */
    public function setPppoeProfile(Customer $customer, string $profile): void
    {
        $secret = $this->findPppoeSecret($customer->pppoe_username);

        if ($secret === null) {
            $this->client()->command('/ppp/secret/add', [
                'name' => $customer->pppoe_username,
                'password' => $customer->pppoe_password,
                'service' => 'pppoe',
                'profile' => $profile,
                'comment' => "{$customer->customer_code} {$customer->name}",
            ]);

            return;
        }

        if (($secret['profile'] ?? null) === $profile) {
            return;
        }

        $this->client()->command('/ppp/secret/set', ['.id' => $secret['.id'], 'profile' => $profile]);
        $this->kickActiveSession($customer);
    }

    /**
     * Putus sesi PPPoE aktif agar profile baru langsung berlaku.
     */
    public function kickActiveSession(Customer $customer, ?string $username = null): void
    {
        $sessions = $this->client()->command('/ppp/active/print', where: ['name' => $username ?? $customer->pppoe_username]);

        foreach ($sessions as $session) {
            $this->client()->command('/ppp/active/remove', ['.id' => $session['.id']]);
        }
    }

    /**
     * Nonaktifkan PPP secret pelanggan dan putus sesinya.
     */
    public function disablePppoeSecret(Customer $customer): void
    {
        $secret = $this->findPppoeSecret($customer->pppoe_username);

        if ($secret !== null) {
            $this->client()->command('/ppp/secret/set', ['.id' => $secret['.id'], 'disabled' => 'yes']);
        }

        $this->kickActiveSession($customer);
    }

    /**
     * Hapus PPP secret berdasarkan username, mis. saat pelanggan dipindah ke router lain.
     */
    public function removePppoeSecret(string $username): void
    {
        $secret = $this->findPppoeSecret($username);

        if ($secret !== null) {
            $this->client()->command('/ppp/secret/remove', ['.id' => $secret['.id']]);
        }

        foreach ($this->client()->command('/ppp/active/print', where: ['name' => $username]) as $session) {
            $this->client()->command('/ppp/active/remove', ['.id' => $session['.id']]);
        }
    }

    /**
     * Nama hotspot user yang sudah ada dengan komentar tertentu (mis. kode batch).
     *
     * @return list<string>
     */
    public function hotspotUserNames(string $comment): array
    {
        return array_values(array_map(
            fn (array $user): string => $user['name'] ?? '',
            $this->client()->command('/ip/hotspot/user/print', where: ['comment' => $comment]),
        ));
    }

    /**
     * Buat hotspot user dari voucher dengan batas uptime dan kuota profilnya.
     */
    public function createHotspotUser(Voucher $voucher, HotspotProfile $profile, string $comment): void
    {
        $attributes = [
            'name' => $voucher->code,
            'password' => $voucher->password,
            'profile' => $profile->mikrotik_profile,
            'comment' => $comment,
        ];

        if ($profile->uptime_limit_minutes !== null) {
            $attributes['limit-uptime'] = $profile->uptime_limit_minutes.'m';
        }

        if ($profile->data_limit_mb !== null) {
            $attributes['limit-bytes-total'] = $profile->data_limit_mb * 1024 * 1024;
        }

        $this->client()->command('/ip/hotspot/user/add', $attributes);
    }

    /**
     * Hapus hotspot user beserta sesi aktifnya (voucher kedaluwarsa).
     */
    public function removeHotspotUser(string $code): void
    {
        foreach ($this->client()->command('/ip/hotspot/user/print', where: ['name' => $code]) as $user) {
            $this->client()->command('/ip/hotspot/user/remove', ['.id' => $user['.id']]);
        }

        foreach ($this->client()->command('/ip/hotspot/active/print', where: ['user' => $code]) as $session) {
            $this->client()->command('/ip/hotspot/active/remove', ['.id' => $session['.id']]);
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function findPppoeSecret(string $username): ?array
    {
        return $this->client()->command('/ppp/secret/print', where: ['name' => $username])[0] ?? null;
    }

    private function client(): RouterOsClient
    {
        return $this->client ??= $this->factory->make($this->router);
    }
}
