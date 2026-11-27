<?php

namespace App\Providers;

use App\Http\Middleware\ThrottlePerRoute;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Semua `throttle:N,M` di route memakai pembatas dengan hitungan per route (lihat ThrottlePerRoute).
        $this->app->bind(ThrottleRequests::class, fn ($app) => new ThrottlePerRoute($app->make(RateLimiter::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Batas kirim WA Gateway (Baileys) agar broadcast massal tidak membuat nomor diblokir WhatsApp.
        RateLimiterFacade::for('whatsapp', fn () => Limit::perMinute(20));

        // Telegram membatasi sekitar 30 pesan per detik untuk satu bot; sisakan ruang untuk balasan bot dan peringatan admin.
        RateLimiterFacade::for('telegram', fn () => Limit::perSecond(20));

        // Owner memiliki akses penuh ke seluruh sistem, termasuk permission yang ditambahkan kemudian.
        // Hanya pengguna admin yang bisa menjadi Owner; sesi member VPN tidak pernah mendapat akses ini.
        Gate::before(fn (object $user): ?bool => $user instanceof User && $user->hasRole('Owner') ? true : null);
    }
}
