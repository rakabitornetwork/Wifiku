<?php

namespace App\Services\Voucher;

use InvalidArgumentException;

/**
 * Pembuat kode voucher hotspot. Karakter yang mudah tertukar (0/O, 1/I/l) dibuang
 * dari set huruf dan angka agar kode mudah dibaca dan diketik dari voucher cetak.
 */
class VoucherCodeGenerator
{
    /**
     * @var array<string, string>
     */
    public const CHARSETS = [
        'alphanumeric' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        'mixed' => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789',
        'numeric' => '0123456789',
        'alpha' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
    ];

    /**
     * Buat satu kode voucher acak, diawali prefix bila ada.
     */
    public function generate(int $length, string $charset, ?string $prefix = null): string
    {
        $characters = self::CHARSETS[$charset] ?? throw new InvalidArgumentException("Charset voucher [{$charset}] tidak dikenal.");

        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return strtoupper((string) $prefix).$code;
    }
}
