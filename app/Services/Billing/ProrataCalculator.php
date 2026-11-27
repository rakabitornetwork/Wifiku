<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Perhitungan prorata murni: round(harga x hari_terpakai / jumlah_hari_periode).
 * Hari dihitung inklusif: periode 1–30 November = 30 hari.
 */
class ProrataCalculator
{
    /**
     * Hitung tagihan prorata dari tanggal mulai layanan sampai akhir periode.
     *
     * @return array{amount: int, days: int, period_days: int}
     */
    public function calculate(int $monthlyPrice, CarbonImmutable $startDate, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $periodStart = $periodStart->startOfDay();
        $periodEnd = $periodEnd->startOfDay();
        $startDate = $startDate->startOfDay();

        if ($periodEnd->lessThan($periodStart)) {
            throw new InvalidArgumentException('Akhir periode tidak boleh sebelum awal periode.');
        }

        $periodDays = $this->inclusiveDays($periodStart, $periodEnd);
        $start = $startDate->max($periodStart);
        $days = $start->greaterThan($periodEnd) ? 0 : $this->inclusiveDays($start, $periodEnd);

        return [
            'amount' => (int) round($monthlyPrice * $days / $periodDays),
            'days' => $days,
            'period_days' => $periodDays,
        ];
    }

    /**
     * Siklus tagihan yang memuat $date untuk tanggal tagihan $billingDay (1–28): mulai pada billing_day,
     * berakhir sehari sebelum billing_day bulan berikutnya.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function cycleContaining(int $billingDay, CarbonImmutable $date): array
    {
        $date = $date->startOfDay();
        $start = $date->day >= $billingDay
            ? $date->setDay($billingDay)
            : $date->startOfMonth()->subMonthNoOverflow()->setDay($billingDay);

        return [$start, $start->addMonthNoOverflow()->subDay()];
    }

    private function inclusiveDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) round($from->diffInDays($to)) + 1;
    }
}
