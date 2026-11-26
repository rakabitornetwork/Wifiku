<?php

use App\Support\TrustedProxies;

return [

    /*
    |--------------------------------------------------------------------------
    | Proxy tepercaya
    |--------------------------------------------------------------------------
    |
    | Diisi dari TRUSTED_PROXIES di .env (contoh: "cloudflare", "cloudflare,private", "private",
    | atau daftar IP/CIDR). Dibaca oleh middleware TrustProxies Laravel agar IP pengunjung, skema https,
    | dan host asli diambil dari header X-Forwarded-* proxy, bukan IP proxy itu sendiri.
    |
    */

    'proxies' => TrustedProxies::resolve(env('TRUSTED_PROXIES')),

];
