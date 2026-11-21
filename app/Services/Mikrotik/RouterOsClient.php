<?php

namespace App\Services\Mikrotik;

/**
 * Satu koneksi API ke router MikroTik. Dipisah dari library agar MikrotikService dapat diuji tanpa router sungguhan.
 */
interface RouterOsClient
{
    /**
     * Kirim satu perintah RouterOS dan kembalikan baris hasilnya.
     *
     * @param  array<string, string|int>  $attributes  Atribut tulis (=key=value)
     * @param  array<string, string|int>  $where  Filter baca (?key=value)
     * @return list<array<string, string>>
     *
     * @throws MikrotikConnectionException Koneksi, login, atau timeout gagal.
     * @throws MikrotikCommandException Router menolak perintah (!trap).
     */
    public function command(string $path, array $attributes = [], array $where = []): array;
}
