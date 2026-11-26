<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live trafik router
    |--------------------------------------------------------------------------
    |
    | Satu aliran (SSE) memakai satu koneksi API ke router untuk banyak cuplikan, lalu ditutup dan disambung ulang
    | oleh browser. Tiap aliran yang terbuka menahan satu proses PHP, jadi jumlah serentaknya dibatasi.
    |
    */

    // Lama satu aliran sebelum ditutup dan browser menyambung ulang (detik).
    'stream_seconds' => 90,

    // Jeda antar cuplikan (milidetik).
    'interval_ms' => 2000,

    // Batas jumlah cuplikan per aliran; null = hanya dibatasi stream_seconds (dipakai pengujian).
    'max_samples' => null,

    // Jumlah aliran yang boleh terbuka bersamaan untuk seluruh pengguna.
    'max_streams' => 10,

];
