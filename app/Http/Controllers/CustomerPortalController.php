<?php

namespace App\Http\Controllers;

use App\Actions\Payment\CreateOnlinePayment;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Http\Middleware\EnsureCustomerPortalSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Services\GenieAcs\GenieAcsException;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\Portal\CustomerLoginService;
use App\Services\Portal\CustomerOnuService;
use App\Services\Settings\SettingService;
use App\Support\CompanyBranding;
use App\Support\PaymentChannelCatalog;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Portal pelanggan PPPoE setelah login: tagihan, status ONU, dan ganti SSID/password Wi-Fi.
 * Semua data diambil dari pelanggan di sesi; tidak ada id pelanggan atau perangkat yang diterima dari browser.
 */
class CustomerPortalController extends Controller
{
    public function dashboard(Request $request, CustomerOnuService $onu, SettingService $settings, CustomerLoginService $login): Response
    {
        $customer = $this->customer($request);
        $asset = $onu->assetFor($customer);
        $gateway = PaymentGateway::where('is_active', true)->orderBy('sort_order')->first();
        $csNumber = preg_replace('/\D+/', '', (string) $settings->get('company', 'cs_whatsapp'));

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNull('vpn_account_id')
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->latest('period_start')
            ->limit(12)
            ->get(['id', 'invoice_number', 'period_start', 'period_end', 'due_date', 'total', 'amount_paid', 'status', 'paid_at'])
            ->map(fn (Invoice $invoice): array => [
                'invoice_number' => $invoice->invoice_number,
                'period_start' => $invoice->period_start->toDateString(),
                'period_end' => $invoice->period_end->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'total' => $invoice->total,
                'outstanding' => max(0, $invoice->total - $invoice->amount_paid),
                'status' => $invoice->status->value,
                'is_overdue' => $invoice->status !== InvoiceStatus::Paid && $invoice->due_date->isPast(),
                'paid_at' => $invoice->paid_at?->toDateString(),
            ])->values();

        return Inertia::render('Public/Portal/Dashboard', [
            'company' => CompanyBranding::data($settings),
            'customer' => [
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'package' => $customer->package?->name,
                'address' => collect([$customer->address, $customer->village])->filter()->implode(', ') ?: null,
                'status' => $customer->status->value,
                'installed_at' => $customer->installed_at?->toDateString(),
                'promise_to_pay_until' => $customer->promise_to_pay_until?->toDateString(),
            ],
            'hasMultipleAccounts' => $login->customersFor((string) $request->session()->get(EnsureCustomerPortalSession::PHONE_KEY))->count() > 1,
            'invoices' => $invoices,
            'outstanding' => $invoices->where('status', '!=', InvoiceStatus::Paid->value)->sum('outstanding'),
            'payment' => [
                'available' => $gateway !== null && ! empty($gateway->enabled_channels),
                'channels' => PaymentChannelCatalog::options($gateway?->enabled_channels),
            ],
            'onu' => $asset === null ? null : [
                'brand' => $asset->brand,
                'model' => $asset->model,
                'serial_number' => $asset->serial_number,
                'mac_address' => $asset->mac_address,
                'installed_at' => $asset->installed_at?->toDateString(),
            ],
            'canChangeWifi' => $onu->canChangeWifi($customer) && $customer->status === CustomerStatus::Active,
            'whatsappUrl' => $csNumber !== ''
                ? 'https://wa.me/'.$csNumber.'?text='.rawurlencode("Halo, saya pelanggan {$customer->customer_code}. Mohon bantuan terkait layanan internet saya.")
                : null,
        ]);
    }

    /**
     * Status ONU langsung dari GenieACS, dimuat terpisah setelah halaman tampil agar dashboard tidak menunggu perangkat.
     */
    public function onu(Request $request, CustomerOnuService $onu): JsonResponse
    {
        return response()->json($onu->live($this->customer($request)), headers: ['Cache-Control' => 'no-store, private']);
    }

    public function updateWifi(Request $request, CustomerOnuService $onu, GenieAcsService $genieAcs): RedirectResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'ssid' => ['required', 'string', 'min:1', 'max:32', 'regex:/^[\x20-\x7E]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:63', 'regex:/^[\x20-\x7E]+$/', 'confirmed'],
        ], [
            'ssid.regex' => 'Nama WiFi hanya boleh berisi huruf, angka, spasi, dan simbol biasa.',
            'password.regex' => 'Password hanya boleh berisi huruf, angka, dan simbol biasa.',
            'password.min' => 'Password WiFi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        if ($customer->status !== CustomerStatus::Active) {
            Toast::error('Pengaturan WiFi hanya bisa diubah saat layanan Anda aktif. Selesaikan tagihan atau hubungi CS.');

            return back();
        }

        $asset = $onu->assetFor($customer);

        if ($asset === null || ! $genieAcs->isConfigured()) {
            Toast::error('ONU Anda belum bisa diatur dari portal. Hubungi CS.');

            return back();
        }

        try {
            $applied = $genieAcs->updateWifi($asset, $data['ssid'], $data['password']);
        } catch (GenieAcsException $exception) {
            Log::warning('Pelanggan gagal mengganti WiFi lewat portal.', ['customer_id' => $customer->id, 'error' => $exception->getMessage()]);
            Toast::error('Pengaturan WiFi belum bisa dikirim ke ONU. Coba lagi beberapa saat lagi atau hubungi CS.');

            return back();
        }

        activity('Portal Pelanggan')->performedOn($customer)->withProperties(['ssid' => $data['ssid']])->log('Pelanggan mengganti SSID dan password WiFi');

        Toast::success($applied
            ? 'WiFi diperbarui. Perangkat Anda akan terputus sebentar; sambungkan lagi dengan nama dan password baru.'
            : 'ONU sedang tidak terhubung. Pengaturan baru diterapkan otomatis saat ONU online kembali.');

        return back();
    }

    public function pay(Request $request, CreateOnlinePayment $createOnlinePayment): SymfonyResponse|RedirectResponse
    {
        $customer = $this->customer($request);
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
            'channel' => ['required', 'string', 'max:50'],
        ]);

        $invoice = $customer->invoices()->whereNull('vpn_account_id')->where('invoice_number', $validated['invoice_number'])->firstOrFail();

        try {
            $payment = $createOnlinePayment->handle($invoice, $validated['channel']);
        } catch (PaymentGatewayException $exception) {
            Log::warning('Gagal membuat transaksi pembayaran online dari portal.', ['invoice_id' => $invoice->id, 'error' => $exception->getMessage()]);
            Toast::error('Pembayaran online sedang tidak dapat diproses. Silakan coba lagi atau hubungi CS.');

            return back();
        }

        if (blank($payment->checkout_url)) {
            Toast::error('Gateway tidak mengembalikan halaman pembayaran. Silakan hubungi CS.');

            return back();
        }

        return Inertia::location($payment->checkout_url);
    }

    private function customer(Request $request): Customer
    {
        return $request->attributes->get('portalCustomer');
    }
}
