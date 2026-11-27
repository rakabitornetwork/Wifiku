<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VpnAccountStatus;
use App\Http\Requests\VpnAccountRequest;
use App\Jobs\Vpn\RemoveVpnAccountJob;
use App\Jobs\Vpn\SyncVpnAccountJob;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\VpnAccount;
use App\Services\Vpn\VpnScriptBuilder;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class VpnAccountController extends Controller
{
    public function store(VpnAccountRequest $request): RedirectResponse
    {
        $account = VpnAccount::create($request->payload());

        SyncVpnAccountJob::dispatch($account->id, $account->server->router_id);

        Toast::success('Akun VPN dibuat; dipasang ke router server lewat antrean.');

        return back();
    }

    public function update(VpnAccountRequest $request, VpnAccount $account): RedirectResponse
    {
        $previousUsername = $account->username;

        $account->update($request->payload());

        SyncVpnAccountJob::dispatch($account->id, $account->server->router_id, $previousUsername);

        Toast::success('Akun VPN diperbarui; router server disinkronkan lewat antrean.');

        return back();
    }

    public function destroy(VpnAccount $account): RedirectResponse
    {
        $invoices = $account->invoices()->withTrashed()->get();

        // Transaksi online yang masih berlaku bisa dibayar pelanggan kapan saja; menghapus akun sekarang akan membuat
        // uang masuk ke tagihan yang sudah dibatalkan dan tanpa pemilik.
        $hasLivePayment = Payment::query()
            ->whereIn('invoice_id', $invoices->modelKeys())
            ->where('status', PaymentStatus::Pending)
            ->where('method', PaymentMethod::Gateway)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        if ($hasLivePayment) {
            Toast::error('Akun VPN ini masih punya transaksi pembayaran online yang belum kedaluwarsa. Tunggu sampai kedaluwarsa atau sampai dibayar, lalu hapus.');

            return back();
        }

        if ($invoices->isNotEmpty()) {
            if ($account->status !== VpnAccountStatus::Disabled) {
                Toast::error('Akun VPN ini punya riwayat tagihan. Ubah statusnya menjadi Nonaktif lebih dulu, lalu hapus.');

                return back();
            }

            if ($invoices->contains(fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::Partial)) {
                Toast::error('Akun VPN ini masih punya tagihan yang dibayar sebagian. Selesaikan atau batalkan tagihan itu lebih dulu.');

                return back();
            }

            // Riwayat tagihan dipertahankan (relasi ke akun dilepas oleh database); identitas akun dicatat di catatan tagihan.
            DB::transaction(function () use ($account, $invoices): void {
                $label = "Akun VPN dihapus: {$account->name} ({$account->username})";

                foreach ($invoices as $invoice) {
                    $invoice->notes = trim(($invoice->notes ? $invoice->notes."\n" : '').$label);

                    if ($invoice->status === InvoiceStatus::Unpaid) {
                        $invoice->status = InvoiceStatus::Cancelled;
                    }

                    $invoice->saveQuietly();
                }
            });
        }

        $account->delete();

        RemoveVpnAccountJob::dispatch($account->vpn_server_id, $account->server->router_id, $account->username);

        Toast::success('Akun VPN dihapus; PPP secret dan port forward-nya dicabut dari router server lewat antrean.');

        return back();
    }

    /**
     * Pasang ulang akun ke router server (mis. setelah router server direset).
     */
    public function sync(VpnAccount $account): RedirectResponse
    {
        SyncVpnAccountJob::dispatch($account->id, $account->server->router_id);

        Toast::success('Sinkronisasi ke router server dijadwalkan.');

        return back();
    }

    /**
     * Script RouterOS untuk router pelanggan dan untuk router server. Berisi password akun.
     */
    public function scripts(VpnAccount $account, VpnScriptBuilder $scripts): JsonResponse
    {
        $account->load('server');

        return response()->json([
            'client' => $scripts->client($account),
            'server' => $scripts->serverAccount($account),
        ]);
    }
}
