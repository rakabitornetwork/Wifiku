<?php

namespace App\Actions\Router;

use App\Actions\Customer\GenerateCustomerCode;
use App\Enums\BillingCycle;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Support\Facades\DB;

/**
 * Impor PPP secret dari router yang sudah berjalan menjadi pelanggan. Hanya membaca router:
 * tidak ada perintah tulis, sehingga layanan pelanggan yang sedang berjalan tidak terganggu.
 */
class ImportPppSecrets
{
    public function __construct(private readonly GenerateCustomerCode $customerCode) {}

    /**
     * Ringkasan isi router untuk ditinjau sebelum impor (password tidak pernah dikirim ke browser).
     *
     * @return array{secrets: list<array<string, mixed>>, profiles: list<array<string, mixed>>, packages: list<array<string, mixed>>, isolir_profile: string, isolir_profile_found: bool}
     */
    public function preview(Router $router): array
    {
        $secrets = $this->readSecrets($router);
        $existing = $this->existingUsernames($router);
        $packages = Package::query()->where('router_id', $router->id)->orderBy('name')->get(['id', 'name', 'price', 'mikrotik_profile']);
        $rates = $this->readProfiles($router);

        $rows = array_map(fn (array $secret): array => [
            'username' => $secret['name'],
            'profile' => $secret['profile'],
            'service' => $secret['service'],
            'disabled' => $secret['disabled'],
            'comment' => $secret['comment'],
            'display_name' => $secret['display_name'],
            'whatsapp' => $secret['whatsapp'],
            'has_password' => $secret['password'] !== '',
            'exists' => isset($existing[$secret['name']]),
        ], $secrets);

        $profiles = collect($rows)->groupBy('profile')->map(fn ($group, string $profile): array => [
            'name' => $profile,
            'count' => $group->where('exists', false)->count(),
            'rate_limit' => $rates[$profile] ?? null,
            'is_isolir' => $router->isIsolirProfile($profile),
            'suggested_package_id' => $packages->firstWhere('mikrotik_profile', $profile)?->id,
        ])->sortByDesc('count')->values()->all();

        return [
            'secrets' => $rows,
            'profiles' => $profiles,
            'packages' => $packages->reject(fn (Package $package) => $router->isIsolirProfile($package->mikrotik_profile))->values()->toArray(),
            'isolir_profile' => $router->isolir_profile,
            'isolir_profile_found' => array_key_exists($router->isolir_profile, $rates),
        ];
    }

    /**
     * @param  array<string, array{package: string, price?: int|string|null}>  $mappings  Per nama profile: '' (lewati), 'new', atau ID paket.
     * @param  array{billing_day: int, due_days: int, bill_from: string}  $defaults  bill_from: 'next_cycle' (tagihan pertama di siklus berikutnya) atau 'now'.
     * @return array{imported: int, existing: int, skipped_profile: int, skipped_no_password: int, packages_created: int}
     */
    public function handle(Router $router, array $mappings, array $defaults): array
    {
        $secrets = $this->readSecrets($router);
        $rates = $this->readProfiles($router);
        $existing = $this->existingUsernames($router);
        $defaults['installed_at'] = $this->billingStart($defaults['bill_from'], $defaults['billing_day']);
        $report = ['imported' => 0, 'existing' => 0, 'skipped_profile' => 0, 'skipped_no_password' => 0, 'packages_created' => 0];

        DB::transaction(function () use ($router, $secrets, $rates, $mappings, $defaults, $existing, &$report): void {
            $packageIds = [];

            foreach ($mappings as $profile => $mapping) {
                $choice = (string) ($mapping['package'] ?? '');

                if ($choice === 'new') {
                    [$packageIds[$profile], $created] = $this->findOrCreatePackage($router, (string) $profile, $rates[$profile] ?? null, (int) ($mapping['price'] ?? 0));
                    $report['packages_created'] += $created ? 1 : 0;
                } elseif ($choice !== '') {
                    $packageIds[$profile] = (int) $choice;
                }
            }

            foreach ($secrets as $secret) {
                if (isset($existing[$secret['name']])) {
                    $report['existing']++;
                } elseif (! isset($packageIds[$secret['profile']])) {
                    $report['skipped_profile']++;
                } elseif ($secret['password'] === '') {
                    // Tanpa password, sinkronisasi berikutnya akan mengosongkan password di router.
                    $report['skipped_no_password']++;
                } else {
                    $this->createCustomer($router, $secret, $packageIds[$secret['profile']], $defaults);
                    $report['imported']++;
                }
            }
        });

        return $report;
    }

    /**
     * @param  array<string, mixed>  $secret
     * @param  array{billing_day: int, due_days: int, installed_at: string}  $defaults
     */
    private function createCustomer(Router $router, array $secret, int $packageId, array $defaults): void
    {
        $isolated = $router->isIsolirProfile($secret['profile']);
        $status = match (true) {
            $secret['disabled'] => CustomerStatus::Inactive,
            $isolated => CustomerStatus::Isolated,
            default => CustomerStatus::Active,
        };

        Customer::create([
            'customer_code' => $this->customerCode->handle(),
            'router_id' => $router->id,
            'package_id' => $packageId,
            'name' => $secret['display_name'],
            'whatsapp' => $secret['whatsapp'],
            'pppoe_username' => $secret['name'],
            'pppoe_password' => $secret['password'],
            'billing_cycle' => BillingCycle::Fixed,
            'billing_day' => $defaults['billing_day'],
            'due_days' => $defaults['due_days'],
            'installed_at' => $defaults['installed_at'],
            'status' => $status,
            'isolated_at' => $status === CustomerStatus::Isolated ? now() : null,
            'router_synced_at' => now(),
        ]);
    }

    /**
     * Pelanggan lama yang diberi tanggal pasang hari ini akan langsung ditagih prorata dan dikirimi WhatsApp,
     * jadi secara bawaan penagihan dimulai pada tanggal tagihan berikutnya.
     */
    private function billingStart(string $billFrom, int $billingDay): string
    {
        $today = now()->startOfDay();

        if ($billFrom === 'now') {
            return $today->toDateString();
        }

        $next = $today->day >= $billingDay ? $today->copy()->addMonthNoOverflow() : $today->copy();

        return $next->setDay($billingDay)->toDateString();
    }

    /**
     * @return array{0: int, 1: bool} ID paket dan apakah baru dibuat.
     */
    private function findOrCreatePackage(Router $router, string $profile, ?string $rateLimit, int $price): array
    {
        $package = Package::query()->where('router_id', $router->id)->where('mikrotik_profile', $profile)->first();

        if ($package !== null) {
            return [$package->id, false];
        }

        $package = Package::create(['router_id' => $router->id, 'name' => $profile, 'mikrotik_profile' => $profile, 'rate_limit' => $rateLimit, 'price' => $price, 'is_active' => true]);

        return [$package->id, true];
    }

    /**
     * @return array<string, true>
     */
    private function existingUsernames(Router $router): array
    {
        return Customer::withTrashed()->where('router_id', $router->id)->pluck('pppoe_username')->flip()->map(fn () => true)->all();
    }

    /**
     * Nama PPP profile di router beserta rate-limit-nya (null bila tidak ada).
     *
     * @return array<string, string|null>
     */
    private function readProfiles(Router $router): array
    {
        $profiles = [];

        foreach (MikrotikService::forRouter($router)->pppProfiles() as $profile) {
            $profiles[$profile['name']] = filled($profile['rate-limit'] ?? null) ? $profile['rate-limit'] : null;
        }

        return $profiles;
    }

    /**
     * Secret PPPoE dengan nama pelanggan dan nomor WhatsApp yang diturunkan dari kolom comment.
     *
     * @return list<array{name: string, password: string, profile: string, service: string, disabled: bool, comment: string, display_name: string, whatsapp: string}>
     */
    private function readSecrets(Router $router): array
    {
        $secrets = [];

        foreach (MikrotikService::forRouter($router)->pppSecrets() as $row) {
            $service = $row['service'] ?? 'any';

            // Hanya secret PPPoE; l2tp/ovpn/sstp biasanya akun VPN, bukan pelanggan.
            if (! in_array($service, ['pppoe', 'any'], true) || blank($row['name'] ?? null)) {
                continue;
            }

            $comment = trim($row['comment'] ?? '');
            $phone = $this->extractPhone($comment);
            $name = trim((string) preg_replace('/\s+/', ' ', $phone === null ? $comment : str_replace($phone['raw'], ' ', $comment)), " \t-|,;:()");

            $secrets[] = [
                'name' => $row['name'],
                'password' => $row['password'] ?? '',
                'profile' => $row['profile'] ?? 'default',
                'service' => $service,
                'disabled' => ($row['disabled'] ?? 'false') === 'true' || ($row['disabled'] ?? '') === 'yes',
                'comment' => $comment,
                'display_name' => $name !== '' ? $name : $row['name'],
                'whatsapp' => $phone['normalized'] ?? '',
            ];
        }

        return $secrets;
    }

    /**
     * @return array{raw: string, normalized: string}|null
     */
    private function extractPhone(string $comment): ?array
    {
        if (preg_match('/(?<!\d)(?:\+?62|0)8\d[\d\s-]{7,13}\d(?!\d)/', $comment, $match) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $match[0]);
        $normalized = str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;

        return preg_match('/^62\d{8,13}$/', $normalized) === 1 ? ['raw' => $match[0], 'normalized' => $normalized] : null;
    }
}
