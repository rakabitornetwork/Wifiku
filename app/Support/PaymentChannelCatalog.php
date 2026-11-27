<?php

namespace App\Support;

/**
 * Kamus kode channel gateway (Tripay, Duitku, Midtrans) menjadi nama yang dimengerti pelanggan, lengkap dengan
 * kelompoknya. Kode yang tidak dikenal tetap tampil sebagai nama yang dirapikan di kelompok "Lainnya".
 */
class PaymentChannelCatalog
{
    public const GROUP_QRIS = 'QRIS';

    public const GROUP_BANK = 'Transfer Bank (Virtual Account)';

    public const GROUP_STORE = 'Bayar di Minimarket';

    public const GROUP_EWALLET = 'Dompet Digital';

    public const GROUP_CARD = 'Kartu Kredit';

    public const GROUP_OTHER = 'Lainnya';

    /**
     * @var array<string, array{0: string, 1: string}> kode (huruf besar) => [nama, kelompok]
     */
    private const CHANNELS = [
        // QRIS
        'QRIS' => ['QRIS (semua e-wallet & m-banking)', self::GROUP_QRIS],
        'QRISC' => ['QRIS (semua e-wallet & m-banking)', self::GROUP_QRIS],
        'QRIS2' => ['QRIS (semua e-wallet & m-banking)', self::GROUP_QRIS],
        'OTHER_QRIS' => ['QRIS (semua e-wallet & m-banking)', self::GROUP_QRIS],
        'SP' => ['QRIS (ShopeePay)', self::GROUP_QRIS],
        'NQ' => ['QRIS (semua e-wallet & m-banking)', self::GROUP_QRIS],
        'GQ' => ['QRIS (GudangVoucher)', self::GROUP_QRIS],
        'SQ' => ['QRIS (Nobu)', self::GROUP_QRIS],
        // Virtual account — Tripay
        'BCAVA' => ['BCA', self::GROUP_BANK],
        'BRIVA' => ['BRI', self::GROUP_BANK],
        'BNIVA' => ['BNI', self::GROUP_BANK],
        'MANDIRIVA' => ['Mandiri', self::GROUP_BANK],
        'PERMATAVA' => ['Permata', self::GROUP_BANK],
        'CIMBVA' => ['CIMB Niaga', self::GROUP_BANK],
        'MYBVA' => ['Maybank', self::GROUP_BANK],
        'MUAMALATVA' => ['Bank Muamalat', self::GROUP_BANK],
        'DANAMONVA' => ['Danamon', self::GROUP_BANK],
        'BSIVA' => ['BSI (Bank Syariah Indonesia)', self::GROUP_BANK],
        'SAMPOERNAVA' => ['Bank Sahabat Sampoerna', self::GROUP_BANK],
        'SMSVA' => ['Bank Sinarmas', self::GROUP_BANK],
        'OTHERBANKVA' => ['Bank Lainnya', self::GROUP_BANK],
        // Virtual account — Duitku
        'BC' => ['BCA', self::GROUP_BANK],
        'M2' => ['Mandiri', self::GROUP_BANK],
        'VA' => ['Maybank', self::GROUP_BANK],
        'I1' => ['BNI', self::GROUP_BANK],
        'B1' => ['CIMB Niaga', self::GROUP_BANK],
        'BT' => ['Permata', self::GROUP_BANK],
        'A1' => ['ATM Bersama', self::GROUP_BANK],
        'AG' => ['Bank Artha Graha', self::GROUP_BANK],
        'NC' => ['Bank Neo Commerce', self::GROUP_BANK],
        'BR' => ['BRI', self::GROUP_BANK],
        'S1' => ['Bank Sahabat Sampoerna', self::GROUP_BANK],
        'DM' => ['Danamon', self::GROUP_BANK],
        'BV' => ['BSI (Bank Syariah Indonesia)', self::GROUP_BANK],
        // Virtual account — Midtrans
        'BCA_VA' => ['BCA', self::GROUP_BANK],
        'BNI_VA' => ['BNI', self::GROUP_BANK],
        'BRI_VA' => ['BRI', self::GROUP_BANK],
        'PERMATA_VA' => ['Permata', self::GROUP_BANK],
        'CIMB_VA' => ['CIMB Niaga', self::GROUP_BANK],
        'ECHANNEL' => ['Mandiri', self::GROUP_BANK],
        // Minimarket
        'ALFAMART' => ['Alfamart', self::GROUP_STORE],
        'ALFAMIDI' => ['Alfamidi', self::GROUP_STORE],
        'INDOMARET' => ['Indomaret', self::GROUP_STORE],
        'FT' => ['Alfamart / Pegadaian / Pos', self::GROUP_STORE],
        // Dompet digital
        'OVO' => ['OVO', self::GROUP_EWALLET],
        'OV' => ['OVO', self::GROUP_EWALLET],
        'DANA' => ['DANA', self::GROUP_EWALLET],
        'DA' => ['DANA', self::GROUP_EWALLET],
        'SHOPEEPAY' => ['ShopeePay', self::GROUP_EWALLET],
        'GOPAY' => ['GoPay', self::GROUP_EWALLET],
        'LINKAJA' => ['LinkAja', self::GROUP_EWALLET],
        'LF' => ['LinkAja (fixed fee)', self::GROUP_EWALLET],
        'LA' => ['LinkAja (percentage fee)', self::GROUP_EWALLET],
        // Kartu
        'VC' => ['Kartu Kredit (Visa / Mastercard / JCB)', self::GROUP_CARD],
        'CREDIT_CARD' => ['Kartu Kredit (Visa / Mastercard / JCB)', self::GROUP_CARD],
    ];

    /**
     * @return array{code: string, label: string, group: string}
     */
    public static function describe(string $code): array
    {
        [$label, $group] = self::CHANNELS[strtoupper($code)] ?? [ucwords(strtolower(str_replace(['_', '-'], ' ', $code))), self::GROUP_OTHER];

        return ['code' => $code, 'label' => $label, 'group' => $group];
    }

    /**
     * @param  array<int, string>|null  $codes
     * @return list<array{code: string, label: string, group: string}>
     */
    public static function options(?array $codes): array
    {
        return array_map(self::describe(...), array_values($codes ?? []));
    }

    /**
     * Nama ramah untuk riwayat pembayaran ("BCA (Transfer Bank)", "QRIS", dst).
     */
    public static function label(string $code): string
    {
        $channel = self::describe($code);

        return $channel['group'] === self::GROUP_BANK ? "{$channel['label']} (Transfer Bank)" : $channel['label'];
    }
}
