<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetDeviceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\IntegrationSettingController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\IsolirController;
use App\Http\Controllers\IsolirPaymentController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\OdpController;
use App\Http\Controllers\OutageController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\RouterConnectionController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\RouterImportController;
use App\Http\Controllers\TopologyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherBatchController;
use App\Http\Controllers\Webhooks\HotspotLoginWebhookController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/isolir/{customer:customer_code}', IsolirController::class)
    ->middleware('throttle:30,1')
    ->name('isolir.show');
Route::post('/isolir/{customer:customer_code}/pay', IsolirPaymentController::class)
    ->middleware('throttle:10,1')
    ->name('isolir.pay');

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/payment/{driver}', PaymentWebhookController::class)->middleware('throttle:120,1')->name('payment');
    Route::match(['get', 'post'], '/hotspot/{router}/login', HotspotLoginWebhookController::class)->middleware('throttle:600,1')->name('hotspot-login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:routers.manage')->group(function () {
        Route::resource('routers', RouterController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/routers/{router}/import-preview', [RouterImportController::class, 'preview'])->name('routers.import-preview');
        Route::post('/routers/{router}/import', [RouterImportController::class, 'store'])->name('routers.import');
        Route::post('/routers/{router}/check', [RouterConnectionController::class, 'check'])->name('routers.check');
        Route::get('/routers/{router}/hotspot-script', [RouterConnectionController::class, 'hotspotScript'])->name('routers.hotspot-script');
    });

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
        Route::post('/assets/{asset}/rx-power', [AssetDeviceController::class, 'rxPower'])->name('assets.rx-power');
        Route::post('/assets/{asset}/wifi', [AssetDeviceController::class, 'wifi'])->name('assets.wifi');
    });

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('can:invoices.view');
    Route::middleware('can:payments.collect')->group(function () {
        Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
    });

    Route::prefix('hotspot')->name('hotspot.')->middleware('can:vouchers.manage')->group(function () {
        Route::resource('profiles', HotspotProfileController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('resellers', ResellerController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('batches', VoucherBatchController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('batches/{batch}/print', [VoucherBatchController::class, 'print'])->name('batches.print');
    });

    Route::get('/topology', [TopologyController::class, 'index'])->name('topology.index')->middleware('can:topology.view');
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

    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:users.manage');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index')->middleware('can:audit.view');

    Route::middleware('can:settings.manage')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
        Route::put('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('payment-gateways.update');
        Route::get('/payment-gateways/{paymentGateway}/channels', [PaymentGatewayController::class, 'channels'])->name('payment-gateways.channels');
        Route::get('/integrations', [IntegrationSettingController::class, 'index'])->name('integrations.index');
        Route::put('/integrations', [IntegrationSettingController::class, 'update'])->name('integrations.update');
        Route::post('/integrations/whatsapp-status', [IntegrationSettingController::class, 'whatsappStatus'])->name('integrations.whatsapp-status');
        Route::post('/integrations/whatsapp-connect', [IntegrationSettingController::class, 'whatsappConnect'])->name('integrations.whatsapp-connect');
        Route::post('/integrations/whatsapp-test', [IntegrationSettingController::class, 'testWhatsapp'])->name('integrations.whatsapp-test');
    });
});
