<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Batas kirim WA Gateway (Baileys) agar broadcast massal tidak membuat nomor diblokir WhatsApp.
        RateLimiter::for('whatsapp', fn () => Limit::perMinute(20));

        // Owner memiliki akses penuh ke seluruh sistem, termasuk permission yang ditambahkan kemudian.
        Gate::before(fn (User $user): ?bool => $user->hasRole('Owner') ? true : null);
    }
}
