<?php

namespace App\Support;

/**
 * Menerjemahkan nilai `TRUSTED_PROXIES` di .env menjadi daftar proxy tepercaya untuk TrustProxies Laravel.
 *
 * Header seperti X-Forwarded-For hanya dipercaya bila permintaan datang langsung dari proxy yang terdaftar di sini;
 * tanpa itu, semua pengunjung di belakang Cloudflare atau Nginx tampak berasal dari satu IP sehingga pembatas
 * permintaan (login, OTP) dibagi bersama, dan IP palsu di header tidak boleh dipercaya dari sembarang pengirim.
 */
class TrustedProxies
{
    /**
     * Rentang IP Cloudflare (https://www.cloudflare.com/ips/). Periksa daftar resmi itu secara berkala bila Cloudflare mengubahnya.
     *
     * @var list<string>
     */
    public const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
        '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /** Alamat proxy di mesin yang sama atau jaringan privat (Nginx/Apache lokal, Docker, tunnel). */
    public const PRIVATE = ['127.0.0.0/8', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fd00::/8'];

    /**
     * Nilai yang dipahami (dipisah koma): `*` (percaya semua pengirim, hanya bila origin tidak bisa diakses langsung),
     * `cloudflare`, `private`, atau IP/CIDR biasa. Kosong berarti tidak ada proxy yang dipercaya.
     *
     * @return list<string>|string|null
     */
    public static function resolve(?string $value): array|string|null
    {
        $tokens = array_values(array_filter(array_map('trim', explode(',', (string) $value))));

        if ($tokens === []) {
            return null;
        }

        if (in_array('*', $tokens, true)) {
            return '*';
        }

        $ips = [];

        foreach ($tokens as $token) {
            $ips = [...$ips, ...match (strtolower($token)) {
                'cloudflare' => self::CLOUDFLARE,
                'private' => self::PRIVATE,
                default => [$token],
            }];
        }

        return array_values(array_unique($ips));
    }
}
