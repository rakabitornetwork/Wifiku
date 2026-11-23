<?php

namespace App\Actions\Router;

use App\Models\HotspotProfile;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;

/**
 * Impor Hotspot User Profile yang sudah ada di router. Hanya membaca router (tidak ada perintah tulis).
 * Harga dan masa aktif diambil dari skrip on-login gaya Mikhmon bila ada; bila tidak, harga 0 dan
 * profil dibuat nonaktif sampai harganya diisi.
 */
class ImportHotspotProfiles
{
    private const BUILT_IN = ['default', 'default-encrypted'];

    private const DEFAULT_VALIDITY_MINUTES = 1440;

    private const UNIT_MINUTES = ['w' => 10080, 'd' => 1440, 'h' => 60, 'm' => 1];

    /**
     * @return list<array{name: string, rate_limit: string|null, shared_users: int, price: int, validity_minutes: int, exists: bool}>
     */
    public function preview(Router $router): array
    {
        $existing = HotspotProfile::query()->where('router_id', $router->id)->pluck('mikrotik_profile')->all();

        return collect(MikrotikService::forRouter($router)->hotspotProfiles())
            ->reject(fn (array $profile): bool => blank($profile['name'] ?? null) || in_array($profile['name'], self::BUILT_IN, true))
            ->map(fn (array $profile): array => [
                'name' => $profile['name'],
                'rate_limit' => filled($profile['rate-limit'] ?? null) ? $profile['rate-limit'] : null,
                'shared_users' => max(1, min(255, (int) ($profile['shared-users'] ?? 1))),
                ...$this->priceAndValidity($profile['on-login'] ?? ''),
                'exists' => in_array($profile['name'], $existing, true),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $names  Nama profile di router yang dipilih untuk diimpor.
     * @return array{imported: int, existing: int}
     */
    public function handle(Router $router, array $names): array
    {
        $report = ['imported' => 0, 'existing' => 0];

        foreach ($this->preview($router) as $profile) {
            if (! in_array($profile['name'], $names, true)) {
                continue;
            }

            if ($profile['exists'] || HotspotProfile::query()->where('router_id', $router->id)->where('name', $profile['name'])->exists()) {
                $report['existing']++;

                continue;
            }

            HotspotProfile::create([
                'router_id' => $router->id,
                'name' => $profile['name'],
                'mikrotik_profile' => $profile['name'],
                'price' => $profile['price'],
                'validity_minutes' => $profile['validity_minutes'],
                'rate_limit' => $profile['rate_limit'],
                'shared_users' => $profile['shared_users'],
                'is_active' => $profile['price'] > 0,
            ]);
            $report['imported']++;
        }

        return $report;
    }

    /**
     * Skrip on-login Mikhmon: `:put (",mode,harga,masa_aktif,harga_jual,...")`.
     *
     * @return array{price: int, validity_minutes: int}
     */
    private function priceAndValidity(string $onLogin): array
    {
        $result = ['price' => 0, 'validity_minutes' => self::DEFAULT_VALIDITY_MINUTES];

        if (! preg_match('/:put\s*\(\s*"([^"]*)"/', $onLogin, $match)) {
            return $result;
        }

        $fields = explode(',', $match[1]);
        $price = $fields[4] ?? '';
        $price = ctype_digit($price) && $price !== '' ? $price : ($fields[2] ?? '');

        if (ctype_digit($price) && $price !== '') {
            $result['price'] = min((int) $price, 10000000);
        }

        $validity = $this->parseDuration($fields[3] ?? '');

        if ($validity !== null) {
            $result['validity_minutes'] = $validity;
        }

        return $result;
    }

    /**
     * Durasi RouterOS (mis. 1d, 12h, 1w2d) menjadi menit, atau null bila tidak dikenali.
     */
    private function parseDuration(string $duration): ?int
    {
        if (! preg_match('/^(\d+[wdhm])+$/', $duration)) {
            return null;
        }

        preg_match_all('/(\d+)([wdhm])/', $duration, $parts, PREG_SET_ORDER);

        $minutes = array_sum(array_map(fn (array $part): int => (int) $part[1] * self::UNIT_MINUTES[$part[2]], $parts));

        return $minutes > 0 ? min($minutes, 5256000) : null;
    }
}
