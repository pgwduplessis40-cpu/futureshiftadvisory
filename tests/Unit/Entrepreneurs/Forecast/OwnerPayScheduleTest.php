<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\CashLedger;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\OwnerPaySchedule;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use PHPUnit\Framework\TestCase;

final class OwnerPayScheduleTest extends TestCase
{
    public function test_negative_reviewed_net_revenue_does_not_trigger_a_higher_owner_payment(): void
    {
        $result = (new OwnerPaySchedule)->calculate(new ForecastCalendar('2027-01', 1), 'policy', '2027-01-01',
            [new OwnerPayStep('2027-01-01', '50', 'base'), new OwnerPayStep('2027-01-01', '100', 'trailing_three_month_average_revenue', '100')],
            ['2026-10' => '-500', '2026-11' => '100', '2026-12' => '100'], 0);
        self::assertSame('pass', $result['status']);
        self::assertSame('-100.00', $result['rates'][0]['trailing_average_revenue']);
        self::assertSame('50.00', $result['rates'][0]['weekly_amount']);
    }

    public function test_weekly_cash_uses_five_then_four_dates_and_not_the_monthly_average(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $result = (new OwnerPaySchedule)->calculate($calendar, 'approved-pay-policy', '2027-01-01', [new OwnerPayStep('2027-01-01', '50', 'base')], [], 0);
        self::assertSame('pass', $result['status']);
        self::assertCount(9, $result['payments']);
        self::assertSame('216.67', $result['rates'][0]['average_monthly_amount']);
        self::assertSame('50.00', $result['rates'][0]['weekly_amount']);
        self::assertNull($result['rates'][0]['trailing_average_revenue']);
        $cash = (new CashLedger)->calculate($calendar, new CashOpening('1000', 'bank', true), $result['payments']);
        self::assertSame(['250.00', '200.00'], array_column($cash['months'], 'payments'));
        self::assertSame('550.00', $cash['months'][1]['closing_cash']);
    }

    public function test_revenue_trigger_reverses_when_the_previous_three_completed_months_fall(): void
    {
        $policy = [new OwnerPayStep('2027-01-01', '50', 'base'), new OwnerPayStep('2027-01-01', '100', 'trailing_three_month_average_revenue', '1000')];
        $revenue = ['2026-10' => '1200', '2026-11' => '1200', '2026-12' => '1200', '2027-01' => '0', '2027-02' => '100000'];
        $result = (new OwnerPaySchedule)->calculate(new ForecastCalendar('2027-01', 2), 'policy', '2027-01-01', $policy, $revenue, 0);
        self::assertSame('100.00', $result['rates'][0]['weekly_amount']);
        self::assertSame('1200.00', $result['rates'][0]['trailing_average_revenue']);
        self::assertSame('50.00', $result['rates'][5]['weekly_amount']);
        self::assertSame('800.00', $result['rates'][5]['trailing_average_revenue']);
        self::assertSame('2027-02-05', $result['rates'][5]['date']);
        self::assertSame('pass', $result['status']);
    }

    public function test_latest_dated_policy_replaces_previous_tiers_and_can_reduce_pay(): void
    {
        $steps = [new OwnerPayStep('2026-12-01', '50', 'base'), new OwnerPayStep('2027-01-15', '20', 'base')];
        $result = (new OwnerPaySchedule)->calculate(new ForecastCalendar('2027-01', 1), 'policy', '2026-12-25', $steps, [], 0);
        self::assertSame(['50.00', '50.00', '20.00', '20.00', '20.00'], array_column($result['rates'], 'weekly_amount'));
    }

    public function test_highest_eligible_threshold_is_selected_independently_of_input_order(): void
    {
        $steps = [new OwnerPayStep('2027-01-01', '150', 'trailing_three_month_average_revenue', '2000'), new OwnerPayStep('2027-01-01', '50', 'base'), new OwnerPayStep('2027-01-01', '100', 'trailing_three_month_average_revenue', '1000')];
        $revenue = ['2026-10' => '2000', '2026-11' => '2000', '2026-12' => '2000'];
        $engine = new OwnerPaySchedule;
        $calendar = new ForecastCalendar('2027-01', 1);
        $result = $engine->calculate($calendar, 'policy', '2027-01-01', $steps, $revenue, 0);
        self::assertSame('150.00', $result['rates'][0]['weekly_amount']);
        self::assertEquals($result, $engine->calculate($calendar, 'policy', '2027-01-01', array_reverse($steps), $revenue, 0));
    }

    public function test_missing_history_threshold_policy_or_rate_does_not_turn_into_zero_pay(): void
    {
        $calendar = new ForecastCalendar('2027-01', 1);
        $cases = [[], [new OwnerPayStep('2027-01-01', null, 'base')],
            [new OwnerPayStep('2027-01-01', '50', 'base'), new OwnerPayStep('2027-01-01', '100', 'trailing_three_month_average_revenue', null)],
            [new OwnerPayStep('2027-01-01', '50', 'base'), new OwnerPayStep('2027-01-01', '100', 'trailing_three_month_average_revenue', '1000')]];
        foreach ($cases as $steps) {
            $result = (new OwnerPaySchedule)->calculate($calendar, 'policy', '2027-01-01', $steps, [], 0);
            self::assertSame('cannot_verify', $result['status']);
            self::assertNull($result['rates'][0]['weekly_amount']);
            self::assertNull($result['rates'][0]['average_monthly_amount']);
            self::assertNull($result['payments'][0]->amount);
        }
    }

    public function test_duplicate_thresholds_with_different_decimal_format_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new OwnerPaySchedule)->calculate(new ForecastCalendar('2027-01', 1), 'policy', '2027-01-01', [
            new OwnerPayStep('2027-01-01', '50', 'trailing_three_month_average_revenue', '1000'),
            new OwnerPayStep('2027-01-01', '100', 'trailing_three_month_average_revenue', '1000.00'),
        ], [], 0);
    }
}
