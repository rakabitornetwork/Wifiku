<?php

namespace App\Services\Legacy;

use App\Models\Customer;
use App\Services\Portal\CustomerLoginService;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Menyalin tanggal tagihan dan nomor WhatsApp pelanggan dari database aplikasi lama ke pelanggan yang sudah ada di sini.
 *
 * Pelanggan dicocokkan lewat username PPPoE (ID di dua database tidak sama). Hanya tiga kolom yang disentuh:
 * whatsapp (hanya bila di sini masih kosong), billing_day, dan installed_at. Kolom installed_at di sini berfungsi sebagai
 * awal penagihan (hasil impor PPP), jadi saat tanggal tagihan berubah ia dipindah ke kemunculan berikutnya tanggal itu,
 * agar invoice pertama berupa siklus penuh pada tanggal tagihan, bukan prorata di tanggal acak.
 */
class LegacyBillingContactSync
{
    /**
     * @var array<string, int>
     */
    private array $report = [
        'cocok' => 0,
        'whatsapp_diisi' => 0,
        'whatsapp_tetap_beda' => 0,
        'whatsapp_lama_kosong' => 0,
        'tanggal_tagihan_diubah' => 0,
        'tanggal_tagihan_sama' => 0,
        'dilewati_sudah_punya_invoice' => 0,
        'dilewati_username_ganda' => 0,
        'tidak_ada_di_database_lama' => 0,
    ];

    public function __construct(private readonly ConnectionInterface $legacy, private readonly ?CarbonImmutable $today = null) {}

    /**
     * Pemanggil bertanggung jawab atas transaksi pada koneksi aplikasi ini.
     *
     * @return array<string, int>
     */
    public function sync(): array
    {
        $today = ($this->today ?? CarbonImmutable::now())->startOfDay();
        $legacy = $this->legacy->table('pppoe_customers')->get(['username', 'phone', 'billing_day'])->keyBy(fn ($row): string => $this->key($row->username));
        $duplicates = Customer::query()->get(['pppoe_username'])->countBy(fn (Customer $customer): string => $this->key($customer->pppoe_username))->filter(fn (int $count): bool => $count > 1);

        foreach (Customer::query()->orderBy('id')->get() as $customer) {
            $key = $this->key($customer->pppoe_username);
            $old = $legacy->get($key);

            if ($old === null) {
                $this->report['tidak_ada_di_database_lama']++;

                continue;
            }

            if ($duplicates->has($key)) {
                $this->report['dilewati_username_ganda']++;

                continue;
            }

            $this->report['cocok']++;
            $changes = [];

            $phone = CustomerLoginService::normalizePhone($old->phone);

            if ($phone === null) {
                $this->report['whatsapp_lama_kosong']++;
            } elseif (blank($customer->whatsapp)) {
                $changes['whatsapp'] = $phone;
                $this->report['whatsapp_diisi']++;
            } elseif (CustomerLoginService::normalizePhone($customer->whatsapp) !== $phone) {
                $this->report['whatsapp_tetap_beda']++;
            }

            $billingDay = min(28, max(1, (int) $old->billing_day));

            if ($customer->billing_day === $billingDay) {
                $this->report['tanggal_tagihan_sama']++;
            } elseif ($customer->invoices()->exists()) {
                $this->report['dilewati_sudah_punya_invoice']++;
            } else {
                $changes['billing_day'] = $billingDay;
                $changes['installed_at'] = $this->nextOccurrence($billingDay, $today)->toDateString();
                $this->report['tanggal_tagihan_diubah']++;
            }

            if ($changes !== []) {
                $customer->update($changes);
            }
        }

        return $this->report;
    }

    /**
     * Tanggal $day pertama yang jatuh setelah $today (bulan ini bila belum lewat, selain itu bulan depan).
     */
    private function nextOccurrence(int $day, CarbonImmutable $today): CarbonImmutable
    {
        $candidate = $today->setDay($day);

        return $candidate->greaterThan($today) ? $candidate : $today->startOfMonth()->addMonthNoOverflow()->setDay($day);
    }

    private function key(?string $username): string
    {
        return strtolower(trim((string) $username));
    }
}
