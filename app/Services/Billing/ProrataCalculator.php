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
     * Siklus tagihan yang memuat $date untuk tanggal tagihan $billingDay (1–31): mulai pada billing_day,
     * berakhir sehari sebelum billing_day bulan berikutnya. Di bulan yang lebih pendek, tanggal tagihan
     * dimundurkan ke hari terakhir bulan itu (tanggal 31 menjadi 30 November atau 28/29 Februari).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function cycleContaining(int $billingDay, CarbonImmutable $date): array
    {
        $date = $date->startOfDay();
        $thisMonth = $this->billingDateInMonth($billingDay, $date);
        $start = $date->greaterThanOrEqualTo($thisMonth)
            ? $thisMonth
            : $this->billingDateInMonth($billingDay, $date->startOfMonth()->subMonth());

        return [$start, $this->billingDateInMonth($billingDay, $start->startOfMonth()->addMonth())->subDay()];
    }

    /**
     * Tanggal tagihan pada bulan yang memuat $month, dibatasi sampai hari terakhir bulan itu.
     */
    public function billingDateInMonth(int $billingDay, CarbonImmutable $month): CarbonImmutable
    {
        $first = $month->startOfMonth();

        return $first->setDay(min($billingDay, $first->daysInMonth));
    }

    private function inclusiveDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) round($from->diffInDays($to)) + 1;
    }
}
