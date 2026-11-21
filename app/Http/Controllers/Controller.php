<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Pilihan jumlah data per halaman yang diterima dari pengguna.
     *
     * @var list<int>
     */
    public const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    /**
     * Jumlah data per halaman dari query `per_page`; nilai di luar pilihan memakai $default.
     */
    protected function perPage(int $default = 15): int
    {
        $requested = request()->integer('per_page');

        return in_array($requested, self::PER_PAGE_OPTIONS, true) ? $requested : $default;
    }
}
