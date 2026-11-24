<?php

namespace App\Http\Controllers;

use App\Actions\Payment\CreateOnlinePayment;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\VpnAccount;
use App\Services\Settings\SettingService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Halaman publik (tanpa login, dibuka lewat token acak) tempat pembeli VPN Tunnel melihat tagihan dan membayarnya.
 */
class VpnPortalController extends Controller
{
    public function show(VpnAccount $account, SettingService $settings): Response
    {
        $invoices = $account->invoices()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->orderBy('due_date')
            ->get(['id', 'invoice_number', 'period_start', 'period_end', 'due_date', 'total', 'amount_paid', 'status'])
            ->map(fn (Invoice $invoice): array => [
                'invoice_number' => $invoice->invoice_number,
                'period_start' => $invoice->period_start->toDateString(),
                'period_end' => $invoice->period_end->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'total' => $invoice->total,
                'outstanding' => $invoice->total - $invoice->amount_paid,
                'status' => $invoice->status->value,
                'is_overdue' => $invoice->due_date->isPast(),
            ]);

        $gateway = PaymentGateway::where('is_active', true)->orderBy('sort_order')->first();
        $csNumber = preg_replace('/\D+/', '', (string) $settings->get('company', 'cs_whatsapp'));

        return Inertia::render('Public/VpnPortal', [
            'company' => $settings->get('company', 'name', config('app.name')),
            'account' => [
                'token' => $account->portal_token,
                'name' => $this->maskName($account->name),
                'username' => $account->username,
                'protocol' => $account->protocol->label(),
                'status' => $account->status->value,
                'expires_at' => $account->expires_at?->toDateString(),
                'monthly_price' => $account->monthly_price,
            ],
            'memberUrl' => $account->vpn_member_id !== null && Auth::guard('member')->id() === $account->vpn_member_id ? route('vpn-member.dashboard') : null,
            'invoices' => $invoices,
            'outstanding' => $invoices->sum('outstanding'),
            'payment' => [
                'available' => $gateway !== null && ! empty($gateway->enabled_channels),
                'channels' => $gateway?->enabled_channels ?? [],
            ],
            'whatsappUrl' => $csNumber !== ''
                ? 'https://wa.me/'.$csNumber.'?text='.rawurlencode("Halo, saya pengguna VPN Tunnel akun {$account->username}. Mohon bantuannya.")
                : null,
        ]);
    }

    public function pay(Request $request, VpnAccount $account, CreateOnlinePayment $createOnlinePayment): SymfonyResponse|RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
            'channel' => ['required', 'string', 'max:50'],
        ]);

        $invoice = $account->invoices()->where('invoice_number', $validated['invoice_number'])->firstOrFail();

        try {
            $payment = $createOnlinePayment->handle($invoice, $validated['channel']);
        } catch (PaymentGatewayException $exception) {
            Log::warning('Gagal membuat transaksi pembayaran VPN.', ['invoice_id' => $invoice->id, 'error' => $exception->getMessage()]);
            Toast::error('Pembayaran online sedang tidak dapat diproses. Silakan coba lagi atau hubungi CS.');

            return back();
        }

        if (blank($payment->checkout_url)) {
            Toast::error('Gateway tidak mengembalikan halaman pembayaran. Silakan hubungi CS.');

            return back();
        }

        return Inertia::location($payment->checkout_url);
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = array_shift($parts);

        return trim($first.' '.collect($parts)->map(fn (string $part): string => Str::substr($part, 0, 1).'***')->implode(' '));
    }
}
