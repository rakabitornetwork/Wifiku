<?php

namespace App\Jobs\Network;

use App\Models\Customer;
use App\Services\Network\IsolationService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Mengembalikan profile PPPoE pelanggan ke paket normal setelah tagihan lunas atau Janji Bayar dibuat.
 */
class ReopenCustomerJob implements ShouldQueue
{
    use Queueable;

    /**
     * Jumlah exception yang diizinkan sebelum job gagal.
     * Release karena antre kunci router tidak dihitung sebagai kegagalan.
     */
    public int $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 15;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(public Customer $customer) {}

    /**
     * Satu router hanya menerima satu perintah API pada satu waktu.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('router:'.$this->customer->router_id))->releaseAfter(10)->expireAfter(30)];
    }

    /**
     * Batas waktu job tetap dicoba ulang selama router sibuk.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    /**
     * Execute the job.
     */
    public function handle(IsolationService $isolation): void
    {
        $customer = $this->customer->fresh();

        if ($customer === null || ! $isolation->canReopen($customer)) {
            return;
        }

        $isolation->reopen($customer);
    }
}
