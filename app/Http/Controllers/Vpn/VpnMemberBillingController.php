<?php

namespace App\Http\Controllers\Vpn;

use App\Actions\Payment\CreateOnlinePayment;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\VpnMember;
use App\Services\Settings\SettingService;
use App\Services\Vpn\VpnBillingPresenter;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Halaman Tagihan di portal pelanggan: semua invoice VPN miliknya, bayar online, dan riwayat pembayaran.
 */
class VpnMemberBillingController extends Controller
{
    public function index(Request $request, SettingService $settings, VpnBillingPresenter $presenter): Response
    {
        $member = $request->user('member');

        $open = $this->invoices($member)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->orderBy('due_date')
            ->get()
            ->map(fn (Invoice $invoice): array => $presenter->row($invoice));

        $history = $this->invoices($member)
            ->whereIn('status', [InvoiceStatus::Paid, InvoiceStatus::Cancelled])
            ->latest('issue_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => $presenter->row($invoice));

        return Inertia::render('Public/Vpn/Billing', [
            'company' => $settings->get('company', 'name', config('app.name')),
            'member' => ['name' => $member->name, 'email' => $member->email],
            'open' => $open,
            'outstanding' => $open->sum('outstanding'),
            'history' => $history,
            'payment' => $presenter->paymentOptions(),
        ]);
    }

    /**
     * Buat transaksi QRIS/VA untuk satu invoice milik member, lalu arahkan ke halaman pembayaran gateway.
     */
    public function pay(Request $request, CreateOnlinePayment $createOnlinePayment): SymfonyResponse|RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
            'channel' => ['required', 'string', 'max:50'],
        ]);

        // Dicari hanya di invoice milik member ini, jadi invoice orang lain selalu 404.
        $invoice = $this->invoices($request->user('member'))->where('invoice_number', $validated['invoice_number'])->firstOrFail();

        try {
            $payment = $createOnlinePayment->handle($invoice, $validated['channel']);
        } catch (PaymentGatewayException $exception) {
            Log::warning('Gagal membuat transaksi pembayaran VPN member.', ['invoice_id' => $invoice->id, 'error' => $exception->getMessage()]);
            Toast::error('Pembayaran online sedang tidak dapat diproses. Silakan coba lagi atau hubungi CS.');

            return back();
        }

        if (blank($payment->checkout_url)) {
            Toast::error('Gateway tidak mengembalikan halaman pembayaran. Silakan hubungi CS.');

            return back();
        }

        return Inertia::location($payment->checkout_url);
    }

    /**
     * @return Builder<Invoice>
     */
    private function invoices(VpnMember $member)
    {
        return Invoice::query()
            ->whereIn('vpn_account_id', $member->accounts()->select('id'))
            ->with(['vpnAccount', 'payments' => fn ($query) => $query->latest('id')]);
    }
}
