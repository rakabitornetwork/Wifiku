<?php

namespace App\Jobs\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Whatsapp\CustomerNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Pengingat pada hari jatuh tempo untuk invoice pelanggan yang belum lunas. Isolir otomatis baru berjalan lewat tengah malam,
 * jadi pelanggan masih punya sisa hari ini untuk membayar. Pelanggan yang sedang dalam masa Janji Bayar tidak diingatkan.
 */
class SendDueDateRemindersJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    public function handle(CustomerNotifier $notifier): void
    {
        $today = CarbonImmutable::today();

        Invoice::query()
            ->with('customer')
            ->whereNotNull('customer_id')
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->whereDate('due_date', $today->toDateString())
            ->whereNull('due_reminder_sent_at')
            ->chunkById(200, function (Collection $invoices) use ($notifier, $today): void {
                foreach ($invoices as $invoice) {
                    $customer = $invoice->customer;

                    if ($customer === null || $customer->trashed()) {
                        continue;
                    }

                    // Klaim atomik: bila job ini berjalan ganda, hanya satu yang mengirim pengingat.
                    $claimed = Invoice::whereKey($invoice->id)->whereNull('due_reminder_sent_at')->update(['due_reminder_sent_at' => now()]);

                    if ($claimed === 1 && ! ($customer->promise_to_pay_until !== null && CarbonImmutable::parse($customer->promise_to_pay_until)->startOfDay()->greaterThanOrEqualTo($today))) {
                        $notifier->invoiceDueToday($invoice);
                    }
                }
            });
    }
}
