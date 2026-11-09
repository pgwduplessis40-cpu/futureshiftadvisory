<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\ExpenseSchedule;
use App\Services\Entrepreneurs\Forecast\FixedCost;
use PHPUnit\Framework\TestCase;

final class FixedCostTest extends TestCase
{
    public function test_a_cost_nobody_has_finished_establishing_is_a_valid_record(): void
    {
        $cost = new FixedCost('lease', 'Signed lease', null, null, null, null, null, null, null);

        self::assertNull($cost->frequency, 'An absent cadence is nobody having said, and must never read as a monthly one.');
        self::assertNull((new ExpenseSchedule)->monthlyRecognition($cost->rate, $cost->quantity, $cost->frequency));
    }

    public function test_a_cost_cannot_stop_being_invoiced_before_it_starts(): void
    {
        $this->expectExceptionMessage('A fixed cost cannot stop being invoiced before it starts.');

        new FixedCost('lease', 'Signed lease', '900', '1', 'monthly', '2026-06-01', '2026-01-01', 20, true);
    }

    public function test_only_cadences_the_engine_can_schedule_and_annualise_are_recognised(): void
    {
        // Fortnightly is a real cadence the gate can annualise, but the
        // recurrence cannot generate it, and `once` is a single charge rather
        // than a recurring cost. Offering either would store an arrangement
        // that refuses the moment it is projected.
        foreach (['fortnightly', 'once', 'daily'] as $cadence) {
            try {
                new FixedCost('lease', 'Signed lease', '900', '1', $cadence, '2026-01-01', '2026-12-01', 20, true);
                self::fail('A cadence the engine cannot schedule must not be stored: '.$cadence);
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('no other cadence is recognised', $e->getMessage());
            }
        }
    }

    public function test_recognition_is_the_annualised_rate_rather_than_the_month_the_invoice_falls_in(): void
    {
        $schedule = new ExpenseSchedule;

        // A monthly charge recognises itself; a quarterly one a third of
        // itself; an annual one a twelfth. The month the invoice lands in
        // does not enter into it, which is what makes the figure a rate.
        self::assertSame('900.00000000', $schedule->monthlyRecognition('900', '1', 'monthly'));
        self::assertSame('300.00000000', $schedule->monthlyRecognition('900', '1', 'quarterly'));
        self::assertSame('75.00000000', $schedule->monthlyRecognition('900', '1', 'annual'));
        self::assertSame('150.00000000', $schedule->monthlyRecognition('50', '3', 'monthly'));
    }

    public function test_recognition_keeps_the_precision_an_exact_allocation_cannot(): void
    {
        // A thousand a year does not divide into twelve months of whole
        // cents. Rounding each month would lose four cents over the year, and
        // giving one month the residual would make that month disagree with
        // the rate every other month is recognised at. The rate is held
        // unrounded; rounding belongs to what is displayed and what is posted.
        self::assertSame('83.33333333', (new ExpenseSchedule)->monthlyRecognition('1000', '1', 'annual'));
    }

    public function test_an_unpriced_cost_recognises_nothing_rather_than_zero(): void
    {
        $schedule = new ExpenseSchedule;

        self::assertNull($schedule->monthlyRecognition(null, '1', 'monthly'));
        self::assertNull($schedule->monthlyRecognition('900', null, 'monthly'));
        self::assertNull($schedule->monthlyRecognition('900', '1', null));
    }
}
