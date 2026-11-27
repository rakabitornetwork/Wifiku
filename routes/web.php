<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetDeviceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\ChatWebhookSettingController;
use App\Http\Controllers\CompanyLogoController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPortalAuthController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GalleryImageController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\IntegrationSettingController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\IsolirController;
use App\Http\Controllers\IsolirPaymentController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LandingImageController;
use App\Http\Controllers\MessageLogController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\OdpController;
use App\Http\Controllers\OutageController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentExtensionController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\RouterConnectionController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\RouterImportController;
use App\Http\Controllers\RouterTrafficController;
use App\Http\Controllers\TelegramSettingController;
use App\Http\Controllers\TopologyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherBatchController;
use App\Http\Controllers\Vpn\VpnLandingController;
use App\Http\Controllers\Vpn\VpnMemberAuthController;
use App\Http\Controllers\Vpn\VpnMemberBillingController;
use App\Http\Controllers\Vpn\VpnMemberPasswordController;
use App\Http\Controllers\Vpn\VpnMemberPortalController;
use App\Http\Controllers\VpnAccountController;
use App\Http\Controllers\VpnController;
use App\Http\Controllers\VpnImportController;
use App\Http\Controllers\VpnMemberController;
use App\Http\Controllers\VpnPortalController;
use App\Http\Controllers\VpnServerController;
use App\Http\Controllers\Webhooks\ChatWebhookController;
use App\Http\Controllers\Webhooks\HotspotLoginWebhookController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// Halaman utama: landing page perusahaan (publik). Panel admin dibuka lewat /login atau tombol Dashboard.
Route::get('/', LandingController::class)->name('home');
Route::get('/galeri/{photo}/{size}', [GalleryImageController::class, 'show'])->middleware('throttle:120,1')->name('gallery.image');
Route::get('/gambar-landing/{slot}', [LandingImageController::class, 'show'])->middleware('throttle:120,1')->name('landing-image.show');
Route::get('/terms-of-service', [LandingController::class, 'terms'])->name('terms');

Route::get('/isolir/{customer:customer_code}', IsolirController::class)
    ->middleware('throttle:30,1')
    ->name('isolir.show');
Route::post('/isolir/{customer:customer_code}/pay', IsolirPaymentController::class)
    ->middleware('throttle:10,1')
    ->name('isolir.pay');

// Halaman masuk bersama untuk pelanggan: tab Internet Rumah (OTP WhatsApp) dan VPN Tunnel (email + password).
// Sesi keduanya tetap terpisah satu sama lain dan dari login admin.
Route::get('/portal', [CustomerPortalAuthController::class, 'showLogin'])->middleware('throttle:30,1')->name('portal.login');
Route::prefix('portal')->name('portal.')->group(function () {
    Route::post('/kode', [CustomerPortalAuthController::class, 'sendCode'])->middleware('throttle:5,1')->name('send-code');
    Route::post('/verifikasi', [CustomerPortalAuthController::class, 'verify'])->middleware('throttle:10,1')->name('verify');
    Route::post('/kirim-ulang', [CustomerPortalAuthController::class, 'resend'])->middleware('throttle:5,1')->name('resend');
});

// Alamat login lama tetap dialihkan: tautan di pesan WhatsApp yang sudah terkirim masih menunjuk ke sini.
Route::redirect('/pelanggan/masuk', '/portal', 301);

// Portal pelanggan PPPoE setelah login: tagihan, status ONU, dan ganti WiFi.
Route::prefix('pelanggan')->name('portal.')->group(function () {
    Route::get('/pilih', [CustomerPortalAuthController::class, 'choose'])->name('choose');
    Route::post('/pilih', [CustomerPortalAuthController::class, 'select'])->middleware('throttle:20,1')->name('select');

    Route::middleware('portal.customer')->group(function () {
        Route::get('/', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/onu', [CustomerPortalController::class, 'onu'])->middleware('throttle:20,1')->name('onu');
        Route::post('/wifi', [CustomerPortalController::class, 'updateWifi'])->middleware('throttle:5,10')->name('wifi');
        Route::post('/bayar', [CustomerPortalController::class, 'pay'])->middleware('throttle:10,1')->name('pay');
        Route::post('/keluar', [CustomerPortalAuthController::class, 'logout'])->name('logout');
    });
});

// Landing page + portal pelanggan VPN Tunnel (daftar sendiri, login terpisah dari admin).
Route::prefix('vpn-tunnel')->name('vpn-member.')->group(function () {
    Route::get('/', VpnLandingController::class)->name('landing');
    Route::get('/daftar', [VpnMemberAuthController::class, 'showRegister'])->middleware('throttle:30,1')->name('register');
    Route::post('/daftar', [VpnMemberAuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::redirect('/masuk', '/portal?layanan=vpn', 301)->name('login');
    Route::post('/masuk', [VpnMemberAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');

    Route::get('/lupa-password', [VpnMemberPasswordController::class, 'showForgot'])->middleware('throttle:30,1')->name('password.request');
    Route::post('/lupa-password', [VpnMemberPasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [VpnMemberPasswordController::class, 'showReset'])->middleware('throttle:30,1')->name('password.reset');
    Route::post('/reset-password', [VpnMemberPasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');

    Route::middleware('auth:member')->group(function () {
        Route::post('/keluar', [VpnMemberAuthController::class, 'logout'])->name('logout');
        Route::get('/akun', [VpnMemberPortalController::class, 'index'])->name('dashboard');
        Route::post('/akun', [VpnMemberPortalController::class, 'store'])->middleware('throttle:10,1')->name('accounts.store');
        Route::get('/tagihan', [VpnMemberBillingController::class, 'index'])->name('billing');
        Route::post('/tagihan/bayar', [VpnMemberBillingController::class, 'pay'])->middleware('throttle:10,1')->name('billing.pay');
        Route::get('/akun/{account}/script', [VpnMemberPortalController::class, 'script'])->name('accounts.script');
    });
});

Route::get('/layanan-vpn/{account:portal_token}', [VpnPortalController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('vpn-portal.show');
Route::post('/layanan-vpn/{account:portal_token}/pay', [VpnPortalController::class, 'pay'])
    ->middleware('throttle:10,1')
    ->name('vpn-portal.pay');

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/payment/{driver}', PaymentWebhookController::class)->middleware('throttle:120,1')->name('payment');
    Route::match(['get', 'post'], '/hotspot/{router}/login', HotspotLoginWebhookController::class)->middleware('throttle:600,1')->name('hotspot-login');
    Route::post('/chat/whatsapp/{secret}', [ChatWebhookController::class, 'whatsapp'])->middleware('throttle:300,1')->name('chat.whatsapp');
    Route::post('/chat/telegram', [ChatWebhookController::class, 'telegram'])->middleware('throttle:300,1')->name('chat.telegram');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/lupa-password', [PasswordResetController::class, 'showForgot'])->middleware('throttle:30,1')->name('password.request');
    Route::post('/lupa-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->middleware('throttle:30,1')->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil/whatsapp', [ProfileController::class, 'updatePhone'])->middleware('throttle:5,1')->name('profile.phone');
    Route::post('/profil/whatsapp/verifikasi', [ProfileController::class, 'verifyPhone'])->middleware('throttle:10,1')->name('profile.phone.verify');
    Route::post('/profil/whatsapp/kirim-ulang', [ProfileController::class, 'resendPhoneCode'])->middleware('throttle:5,1')->name('profile.phone.resend');
    Route::delete('/profil/whatsapp/verifikasi', [ProfileController::class, 'cancelPhone'])->name('profile.phone.cancel');
    Route::post('/profil/avatar', [ProfileController::class, 'updateAvatar'])->middleware('throttle:10,1')->name('profile.avatar');
    Route::delete('/profil/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::get('/avatar/{user}', [AvatarController::class, 'show'])->name('avatar.show');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:5,1')->name('profile.password');

    Route::middleware('can:routers.manage')->group(function () {
        Route::resource('routers', RouterController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/routers/{router}/import-preview', [RouterImportController::class, 'preview'])->name('routers.import-preview');
        Route::post('/routers/{router}/import', [RouterImportController::class, 'store'])->name('routers.import');
        Route::post('/routers/{router}/check', [RouterConnectionController::class, 'check'])->name('routers.check');
        Route::get('/routers/{router}/traffic/interfaces', [RouterTrafficController::class, 'interfaces'])->middleware('throttle:30,1')->name('routers.traffic.interfaces');
        Route::get('/routers/{router}/traffic/stream', [RouterTrafficController::class, 'stream'])->middleware('throttle:20,1')->name('routers.traffic.stream');
        Route::get('/routers/{router}/traffic', [RouterTrafficController::class, 'sample'])->middleware('throttle:120,1')->name('routers.traffic.sample');
        Route::get('/routers/{router}/hotspot-script', [RouterConnectionController::class, 'hotspotScript'])->name('routers.hotspot-script');
    });

    Route::get('/pesan', [MessageLogController::class, 'index'])->name('messages.index')->middleware('can:settings.manage');

    Route::middleware('can:settings.manage')->prefix('galeri-landing')->name('gallery.')->group(function () {
        Route::get('/', [GalleryController::class, 'index'])->name('index');
        Route::post('/', [GalleryController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::put('/{photo}', [GalleryController::class, 'update'])->name('update');
        Route::delete('/{photo}', [GalleryController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:settings.manage')->prefix('ilustrasi-landing')->name('landing-images.')->group(function () {
        Route::get('/', [LandingImageController::class, 'index'])->name('index');
        Route::post('/{slot}/generate', [LandingImageController::class, 'generate'])->middleware('throttle:6,1')->name('generate');
        Route::post('/{slot}/upload', [LandingImageController::class, 'upload'])->middleware('throttle:20,1')->name('upload');
        Route::delete('/{slot}', [LandingImageController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:vpn.manage')->prefix('vpn')->name('vpn.')->group(function () {
        Route::get('/', [VpnController::class, 'index'])->name('index');
        Route::get('servers/{server}/script', [VpnServerController::class, 'script'])->name('servers.script');
        Route::get('servers/{server}/import-preview', [VpnImportController::class, 'preview'])->name('servers.import-preview');
        Route::post('servers/{server}/import', [VpnImportController::class, 'store'])->name('servers.import');
        Route::post('servers/{server}/install', [VpnServerController::class, 'install'])->name('servers.install');
        Route::resource('servers', VpnServerController::class)->only(['store', 'update', 'destroy']);
        Route::get('members', [VpnMemberController::class, 'index'])->name('members.index');
        Route::put('members/{member}', [VpnMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [VpnMemberController::class, 'destroy'])->name('members.destroy');
        Route::post('members/{member}/reset-link', [VpnMemberController::class, 'sendResetLink'])->name('members.reset-link');
        Route::get('accounts/{account}/scripts', [VpnAccountController::class, 'scripts'])->name('accounts.scripts');
        Route::post('accounts/{account}/sync', [VpnAccountController::class, 'sync'])->name('accounts.sync');
        Route::resource('accounts', VpnAccountController::class)->only(['store', 'update', 'destroy']);
    });

    Route::get('/packages/routers/{router}/ppp-profiles', [PackageController::class, 'pppProfiles'])->name('packages.ppp-profiles')->middleware('can:packages.manage');
    Route::patch('/packages/{package}/landing', [PackageController::class, 'toggleLanding'])->name('packages.landing')->middleware('can:packages.manage');
    Route::resource('packages', PackageController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:packages.manage');

    Route::middleware('can:customers.view')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
    });
    Route::delete('/customers', [CustomerController::class, 'destroyMany'])->middleware('can:customers.manage')->name('customers.destroy-many');
    Route::resource('customers', CustomerController::class)->except(['index', 'show'])->middleware('can:customers.manage');

    Route::middleware('can:assets.manage')->group(function () {
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::resource('assets', AssetController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/assets/import-genieacs', [AssetDeviceController::class, 'import'])->name('assets.import-genieacs');
        Route::post('/assets/{asset}/rx-power', [AssetDeviceController::class, 'rxPower'])->name('assets.rx-power');
        Route::get('/assets/{asset}/wifi-details', [AssetDeviceController::class, 'wifiDetails'])->name('assets.wifi-details');
        Route::post('/assets/{asset}/wifi', [AssetDeviceController::class, 'wifi'])->name('assets.wifi');
    });

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('can:invoices.view');
    Route::middleware('can:payments.collect')->group(function () {
        Route::get('/customers/{customer}/extensions', [PaymentExtensionController::class, 'index'])->name('customers.extensions.index');
        Route::post('/customers/{customer}/extensions', [PaymentExtensionController::class, 'store'])->name('customers.extensions.store');
        Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
    });

    Route::prefix('hotspot')->name('hotspot.')->middleware('can:vouchers.manage')->group(function () {
        Route::get('routers/{router}/servers', [HotspotProfileController::class, 'servers'])->name('routers.servers');
        Route::get('profile-import/{router}', [HotspotProfileController::class, 'importPreview'])->name('profiles.import-preview');
        Route::post('profile-import/{router}', [HotspotProfileController::class, 'import'])->name('profiles.import');
        Route::resource('profiles', HotspotProfileController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('resellers', ResellerController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('batches', VoucherBatchController::class)->only(['index', 'create', 'store', 'show']);
        Route::delete('batches/{batch}/vouchers', [VoucherBatchController::class, 'destroyVouchers'])->name('batches.vouchers.destroy');
        Route::delete('routers/{router}/vouchers', [VoucherBatchController::class, 'destroyRouterVouchers'])->name('routers.vouchers.destroy');
        Route::get('batches/{batch}/print', [VoucherBatchController::class, 'print'])->name('batches.print');
    });

    Route::get('/topology', [TopologyController::class, 'index'])->name('topology.index')->middleware('can:topology.view');
    Route::put('/topology/customers/{customer}/cable-route', [TopologyController::class, 'updateCableRoute'])->name('topology.cable-route')->middleware('can:topology.manage');
    Route::resource('odps', OdpController::class)->only(['store', 'update', 'destroy'])->middleware('can:topology.manage');

    Route::middleware('can:outages.manage')->group(function () {
        Route::get('/outages', [OutageController::class, 'index'])->name('outages.index');
        Route::post('/outages', [OutageController::class, 'store'])->name('outages.store');
        Route::post('/outages/{outage}/resolve', [OutageController::class, 'resolve'])->name('outages.resolve');
    });

    Route::middleware('can:expenses.manage')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');
        Route::resource('expense-categories', ExpenseCategoryController::class)->parameters(['expense-categories' => 'category'])->only(['store', 'update', 'destroy']);
    });

    Route::middleware('can:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/voucher-sales', [ReportController::class, 'voucherSales'])->name('voucher-sales');
    });

    Route::post('users/{user}/reset-link', [UserController::class, 'sendResetLink'])->middleware('can:users.manage')->name('users.reset-link');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:users.manage');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index')->middleware('can:audit.view');

    Route::middleware('can:settings.manage')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
        Route::put('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('payment-gateways.update');
        Route::get('/payment-gateways/{paymentGateway}/channels', [PaymentGatewayController::class, 'channels'])->name('payment-gateways.channels');
        Route::get('/integrations', [IntegrationSettingController::class, 'index'])->name('integrations.index');
        Route::get('/notifications', [IntegrationSettingController::class, 'notifications'])->name('notifications.index');
        Route::post('/logo', [CompanyLogoController::class, 'store'])->middleware('throttle:20,1')->name('logo.store');
        Route::delete('/logo', [CompanyLogoController::class, 'destroy'])->name('logo.destroy');
        Route::get('/message-templates', [MessageTemplateController::class, 'index'])->name('message-templates.index');
        Route::put('/message-templates/{template}', [MessageTemplateController::class, 'update'])->name('message-templates.update');
        Route::delete('/message-templates/{template}', [MessageTemplateController::class, 'destroy'])->name('message-templates.destroy');
        Route::post('/whatsapp/webhook', [ChatWebhookSettingController::class, 'whatsappInstall'])->middleware('throttle:10,1')->name('whatsapp.webhook');
        Route::post('/telegram/webhook', [ChatWebhookSettingController::class, 'telegramInstall'])->middleware('throttle:10,1')->name('telegram.webhook');
        Route::post('/telegram/webhook-info', [ChatWebhookSettingController::class, 'telegramInfo'])->middleware('throttle:20,1')->name('telegram.webhook-info');
        Route::post('/telegram/status', [TelegramSettingController::class, 'status'])->middleware('throttle:20,1')->name('telegram.status');
        Route::post('/telegram/test', [TelegramSettingController::class, 'test'])->middleware('throttle:10,1')->name('telegram.test');
        Route::put('/integrations', [IntegrationSettingController::class, 'update'])->name('integrations.update');
        Route::post('/integrations/whatsapp-status', [IntegrationSettingController::class, 'whatsappStatus'])->name('integrations.whatsapp-status');
        Route::post('/integrations/whatsapp-connect', [IntegrationSettingController::class, 'whatsappConnect'])->name('integrations.whatsapp-connect');
        Route::post('/integrations/whatsapp-test', [IntegrationSettingController::class, 'testWhatsapp'])->name('integrations.whatsapp-test');
    });
});
