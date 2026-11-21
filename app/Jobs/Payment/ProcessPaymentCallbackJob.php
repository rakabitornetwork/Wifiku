<?php

namespace App\Jobs\Payment;

use App\Actions\Payment\ApplyPayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentCallback;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Memproses callback gateway yang sudah lolos verifikasi signature secara idempoten.
 * Hasilnya dicatat pada baris payment_callbacks (processed_at / error) untuk audit.
 */
class ProcessPaymentCallbackJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PaymentCallback $paymentCallback,
        public string $merchantRef,
        public PaymentStatus $status,
        public int $amount,
        public ?string $gatewayRef = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ApplyPayment $applyPayment): void
    {
        $payment = Payment::with('paymentGateway')->where('merchant_ref', $this->merchantRef)->first();

        $error = match (true) {
            $payment === null => 'Pembayaran dengan referensi ini tidak ditemukan.',
            $payment->paymentGateway?->driver !== $this->paymentCallback->driver => 'Callback berasal dari gateway yang berbeda dengan transaksi.',
            default => null,
        };

        if ($error === null) {
            $error = match ($this->status) {
                PaymentStatus::Paid => $this->applyPaid($payment, $applyPayment),
                PaymentStatus::Expired, PaymentStatus::Failed => $this->close($payment),
                PaymentStatus::Pending => null,
            };
        }

        $this->paymentCallback->update(['processed_at' => now(), 'error' => $error]);
    }

    private function applyPaid(Payment $payment, ApplyPayment $applyPayment): ?string
    {
        if ($payment->status === PaymentStatus::Paid) {
            return null;
        }

        if ($this->amount !== $payment->amount) {
            return "Nominal callback ({$this->amount}) tidak sama dengan nominal transaksi ({$payment->amount}).";
        }

        $applyPayment->handle($payment, $this->gatewayRef);

        return null;
    }

    private function close(Payment $payment): ?string
    {
        // Callback kedaluwarsa/gagal yang datang setelah lunas tidak boleh membatalkan pembayaran.
        Payment::whereKey($payment->id)->where('status', PaymentStatus::Pending)->update(['status' => $this->status]);

        return null;
    }
}
