<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\ProrataCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProrataCalculatorTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, int, int, int}>
     */
    public static function cases(): array
    {
        return [
            'full period' => ['2026-11-01', '2026-11-01', '2026-11-30', 300000, 30, 300000],
            'half of a 30-day period' => ['2026-11-16', '2026-11-01', '2026-11-30', 300000, 15, 150000],
            'last day only' => ['2026-11-30', '2026-11-01', '2026-11-30', 300000, 1, 10000],
            'cycle crossing month end' => ['2026-11-20', '2026-11-05', '2026-12-04', 310000, 15, 155000],
            'february cycle' => ['2026-02-15', '2026-02-01', '2026-02-28', 280000, 14, 140000],
            'rounds to nearest rupiah' => ['2026-11-11', '2026-11-01', '2026-11-30', 100000, 20, 66667],
            'start before period counts full period' => ['2026-10-20', '2026-11-01', '2026-11-30', 300000, 30, 300000],
            'start after period is free' => ['2026-12-02', '2026-11-01', '2026-11-30', 300000, 0, 0],
        ];
    }

    #[DataProvider('cases')]
    public function test_charges_only_the_days_used_in_the_period(string $start, string $periodStart, string $periodEnd, int $price, int $expectedDays, int $expectedAmount): void
    {
        $result = (new ProrataCalculator)->calculate($price, CarbonImmutable::parse($start), CarbonImmutable::parse($periodStart), CarbonImmutable::parse($periodEnd));

        $this->assertSame($expectedDays, $result['days']);
        $this->assertSame($expectedAmount, $result['amount']);
    }

    public function test_reports_inclusive_period_length(): void
    {
        $result = (new ProrataCalculator)->calculate(1, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-05'), CarbonImmutable::parse('2026-12-04'));

        $this->assertSame(30, $result['period_days']);
    }

    public function test_ignores_time_of_day(): void
    {
        $result = (new ProrataCalculator)->calculate(300000, CarbonImmutable::parse('2026-11-16 23:59'), CarbonImmutable::parse('2026-11-01 08:00'), CarbonImmutable::parse('2026-11-30 01:00'));

        $this->assertSame(15, $result['days']);
    }

    public function test_rejects_period_that_ends_before_it_starts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ProrataCalculator)->calculate(1, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'), CarbonImmutable::parse('2026-11-01'));
    }

    /**
     * @return array<string, array{int, string, string, string}>
     */
    public static function endOfMonthCycles(): array
    {
        return [
            'day 31 in 30-day month' => [31, '2026-11-30', '2026-11-30', '2026-12-30'],
            'day 31 before month end' => [31, '2026-11-15', '2026-10-31', '2026-11-29'],
            'day 31 in december' => [31, '2026-12-31', '2026-12-31', '2027-01-30'],
            'day 31 february' => [31, '2027-02-10', '2027-01-31', '2027-02-27'],
            'day 29 non leap february' => [29, '2027-02-28', '2027-02-28', '2027-03-28'],
            'day 30 march after february' => [30, '2027-03-05', '2027-02-28', '2027-03-29'],
            'day 15 unchanged' => [15, '2026-11-20', '2026-11-15', '2026-12-14'],
        ];
    }

    #[DataProvider('endOfMonthCycles')]
    public function test_cycle_clamps_billing_day_to_month_end(int $day, string $date, string $start, string $end): void
    {
        [$cycleStart, $cycleEnd] = (new ProrataCalculator)->cycleContaining($day, CarbonImmutable::parse($date));

        $this->assertSame($start, $cycleStart->toDateString());
        $this->assertSame($end, $cycleEnd->toDateString());
    }
}
