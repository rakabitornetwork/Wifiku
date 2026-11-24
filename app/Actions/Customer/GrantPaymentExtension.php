<?php

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Jobs\Network\ReopenCustomerJob;
use App\Models\Customer;
use App\Models\PaymentExtension;
use App\Models\User;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\CustomerNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Memberi perpanjangan pembayaran (Janji Bayar) tanpa denda: auto-isolir ditunda sampai tanggal yang disetujui.
 * Admin/Kasir dibatasi pada batas pendek; perpanjangan lebih lama hanya bisa diberikan Owner.
 */
class GrantPaymentExtension
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly CustomerNotifier $notifier,
    ) {}

    /**
     * Batas maksimal perpanjangan (hari dari hari ini) untuk pengguna tersebut.
     */
    public function maxDaysFor(User $user): int
    {
        $key = $user->hasRole('Owner') ? 'extension_owner_max_days' : 'extension_admin_max_days';

        return (int) $this->settings->get('billing', $key);
    }

    /**
     * Tunggakan pelanggan saat ini.
     *
     * @return array{count: int, amount: int}
     */
    public function outstanding(Customer $customer): array
    {
        $invoices = $customer->invoices()->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])->get(['total', 'amount_paid']);

        return [
            'count' => $invoices->count(),
            'amount' => (int) $invoices->sum(fn ($invoice) => max(0, $invoice->total - $invoice->amount_paid)),
        ];
    }

    /**
     * @throws ValidationException
     */
    public function handle(Customer $customer, CarbonImmutable $until, string $reason, User $grantor): PaymentExtension
    {
        $today = CarbonImmutable::today();
        $until = $until->startOfDay();
        $maxDays = $this->maxDaysFor($grantor);

        if ($customer->status === CustomerStatus::Inactive) {
            throw ValidationException::withMessages(['until' => 'Pelanggan nonaktif tidak dapat diberi perpanjangan.']);
        }

        if ($until->lessThanOrEqualTo($today)) {
            throw ValidationException::withMessages(['until' => 'Tanggal perpanjangan harus setelah hari ini.']);
        }

        if ($until->greaterThan($today->addDays($maxDays))) {
            $message = $grantor->hasRole('Owner')
                ? "Perpanjangan maksimal {$maxDays} hari dari hari ini."
                : "Perpanjangan lebih dari {$maxDays} hari harus disetujui Owner.";

            throw ValidationException::withMessages(['until' => $message]);
        }

        $extension = DB::transaction(function () use ($customer, $until, $reason, $grantor, $today): PaymentExtension {
            $previous = $customer->promise_to_pay_until;

            $customer->update(['promise_to_pay_until' => $until->toDateString()]);

            return $customer->paymentExtensions()->create([
                'previous_until' => $previous?->toDateString(),
                'granted_until' => $until->toDateString(),
                'days' => (int) $today->diffInDays($until),
                'reason' => $reason,
                'granted_by' => $grantor->id,
            ]);
        });

        if ($customer->status === CustomerStatus::Isolated) {
            ReopenCustomerJob::dispatch($customer);
        }

        $this->notifier->extensionGranted($customer, $until, $this->outstanding($customer)['amount']);

        return $extension;
    }
}
