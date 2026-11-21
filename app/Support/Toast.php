<?php

namespace App\Support;

use Inertia\Inertia;

class Toast
{
    /**
     * Flash notifikasi sukses yang ditampilkan oleh Toaster di frontend.
     */
    public static function success(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    /**
     * Flash notifikasi gagal yang ditampilkan oleh Toaster di frontend.
     */
    public static function error(string $message): void
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);
    }
}
