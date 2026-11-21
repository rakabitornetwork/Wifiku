<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\RouterStatus;
use App\Jobs\Billing\GenerateMonthlyInvoicesJob;
use App\Jobs\Billing\SendInvoiceRemindersJob;
use App\Jobs\GenieAcs\FetchOntRxPowerJob;
use App\Jobs\Network\CheckRouterStatusJob;
use App\Jobs\Network\ProcessOverdueCustomersJob;
use App\Jobs\Network\SyncIsolationStatusJob;
use App\Jobs\Voucher\ExpireVouchersJob;
use App\Models\Asset;
use App\Models\Router;
use App\Services\GenieAcs\GenieAcsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Billing: invoice siklus berjalan (termasuk prorata), lalu isolir tunggakan, lalu pengingat H-3 di jam kerja.
Schedule::job(new GenerateMonthlyInvoicesJob)->dailyAt('00:10')->name('generate-invoices')->onOneServer()->withoutOverlapping(60);
Schedule::job(new ProcessOverdueCustomersJob)->dailyAt('00:30')->name('auto-isolir')->onOneServer()->withoutOverlapping(60);
Schedule::job(new SendInvoiceRemindersJob)->dailyAt('09:00')->name('invoice-reminders')->onOneServer()->withoutOverlapping(60);

// Jaringan: status router dan voucher hotspot kedaluwarsa.
Schedule::call(function (): void {
    Router::query()->each(fn (Router $router) => CheckRouterStatusJob::dispatch($router));
})->everyFiveMinutes()->name('check-routers')->onOneServer();

// Status pelanggan disamakan dengan profile PPP di router (dua arah); router offline dilewati.
Schedule::call(function (): void {
    Router::query()->where('status', RouterStatus::Online)->each(fn (Router $router) => SyncIsolationStatusJob::dispatch($router));
})->everyFiveMinutes()->name('sync-isolation-status')->onOneServer();

Schedule::job(new ExpireVouchersJob)->everyFiveMinutes()->name('expire-vouchers')->onOneServer();

// TR-069: redaman optik ONT terpasang, hanya bila GenieACS sudah dikonfigurasi.
Schedule::call(function (): void {
    Asset::query()
        ->where('type', AssetType::Ont)
        ->where('status', AssetStatus::Installed)
        ->each(fn (Asset $asset) => FetchOntRxPowerJob::dispatch($asset));
})->hourly()->name('fetch-ont-rx-power')->onOneServer()->when(fn (): bool => app(GenieAcsService::class)->isConfigured());
