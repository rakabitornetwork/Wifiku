<?php

namespace App\Http\Controllers;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

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

    /**
     * Urutkan query sesuai `sort` & `direction` dari URL (judul kolom tabel).
     * Hanya kunci di $columns yang diterima; nilainya nama kolom, Expression, atau closure yang mengembalikan subquery.
     * Tanpa sort valid, urutan bawaan query dipertahankan.
     *
     * @param  Builder<*>  $query
     * @param  array<string, string|Expression|Closure(): (BuilderContract|Expression)>  $columns
     */
    protected function applySort(Builder $query, array $columns): void
    {
        $sort = (string) request()->query('sort');

        if (! array_key_exists($sort, $columns)) {
            return;
        }

        $direction = request()->query('direction') === 'desc' ? 'desc' : 'asc';
        $column = $columns[$sort];

        $query->reorder()
            ->orderBy($column instanceof Closure ? $column() : $column, $direction)
            ->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
    }
}
