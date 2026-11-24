<?php

namespace App\Jobs\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Settings\SettingService;
use App\Services\Whatsapp\CustomerNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Mengirim pengingat H-N (bawaan H-3) untuk invoice belum lunas yang belum pernah diingatkan.
 */
class SendInvoiceRemindersJob implements ShouldQueue
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

    /**
     * Execute the job.
     */
    public function handle(SettingService $settings, CustomerNotifier $notifier): void
    {
        $dueDate = CarbonImmutable::today()->addDays((int) $settings->get('billing', 'reminder_days_before'));

        Invoice::query()
            ->with(['customer', 'vpnAccount'])
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->where('due_date', $dueDate)
            ->whereNull('reminder_sent_at')
            ->chunkById(200, function (Collection $invoices) use ($notifier): void {
                foreach ($invoices as $invoice) {
                    // Klaim atomik: bila job ini berjalan ganda, hanya satu yang mengirim pengingat.
                    $claimed = Invoice::whereKey($invoice->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);

                    if ($claimed === 1 && ($invoice->customer !== null || $invoice->vpnAccount !== null)) {
                        $notifier->invoiceReminder($invoice);
                    }
                }
            });
    }
}
