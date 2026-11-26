<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Pembatas permintaan `throttle:N,M` dengan hitungan terpisah untuk tiap route.
 *
 * Bawaan Laravel membuat kunci hanya dari pengguna (atau IP bila belum login), tanpa route, sehingga semua route
 * ber-throttle berbagi satu hitungan sementara batas N-nya berbeda-beda. Akibatnya permintaan ringan seperti gambar
 * logo ikut menghabiskan jatah aksi ketat (kirim OTP 5 per menit) dan memicu "429 Too Many Requests" yang tampak acak.
 */
class ThrottlePerRoute extends ThrottleRequests
{
    /**
     * @param  Request  $request
     */
    protected function resolveRequestSignature($request): string
    {
        $signature = parent::resolveRequestSignature($request);
        $route = $request->route();

        return $route === null ? $signature : sha1($signature.'|'.implode('|', $route->methods()).'|'.$route->uri());
    }
}
