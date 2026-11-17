<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\RunwayFacts;
use App\Services\Entrepreneurs\ReleaseGate\SourceReference;
use PHPUnit\Framework\TestCase;

/**
 * The runway figures PM-10 has never been given.
 *
 * Nothing on the platform stated how long the money lasts, so the rule could
 * only report its model side missing. These are the two bases it accepts, each
 * carrying the statements that say what it counts - without those a runway is
 * a number whose meaning nobody can check, and the rule refuses to compare
 * unlike measures rather than pretending two of them are one.
 */
final class RunwayFactsTest extends TestCase
{
    public function test_the_forecast_runway_is_the_months_that_close_without_going_negative(): void
    {
        $facts = $this->keyed($this->forecast(['500', '400', '100', '-200', '-900', '-1500']));

        self::assertSame('3', $facts['model.runway_months:forecast_cash']->value);
        self::assertSame('months', $facts['model.runway_months:forecast_cash']->unit);
        self::assertSame('months_closing_without_negative_forecast_cash',
            $facts['model.runway_months:forecast_cash']->basis);
    }

    /** A business already overdrawn at the end of its first month has no
     * months of runway, not one. The count is of months survived. */
    public function test_a_curve_negative_in_its_first_month_has_no_runway(): void
    {
        $facts = $this->keyed($this->forecast(['-50', '-400']));

        self::assertSame('0', $facts['model.runway_months:forecast_cash']->value);
    }

    /**
     * A curve that never goes negative states no runway at all.
     *
     * The model did not find the business solvent indefinitely; it stopped
     * looking at the end of the horizon. Publishing the horizon's length as
     * the runway would let PM-10 report a founder's claim of eighteen months
     * as a demonstrated contradiction of a twelve-month model that is simply
     * silent past month twelve.
     */
    public function test_cash_that_never_runs_out_states_no_runway(): void
    {
        $facts = $this->forecast(['500', '600', '700']);

        self::assertSame([], array_column($facts, 'key'),
            'A question the model stopped asking is not a figure it measured.');
    }

    /** An unreadable month withholds the runway, because the business may or
     * may not survive the month nobody could close. */
    public function test_an_unreadable_month_before_the_cash_runs_out_withholds_the_runway(): void
    {
        self::assertSame([], array_column($this->forecast(['500', null, '-200']), 'key'));
    }

    /** Unless the cash has already run out before it: the business failed
     * inside the part of the curve that could be read, and a gap after that
     * does not unmake it. */
    public function test_a_gap_after_the_cash_has_run_out_does_not_unmake_the_runway(): void
    {
        $facts = $this->keyed($this->forecast(['500', '-100', null]));

        self::assertSame('1', $facts['model.runway_months:forecast_cash']->value);
    }

    /**
     * Whether the curve counts customer receipts is read off the movements in
     * it, not asserted.
     *
     * A reconstruction that states no collection terms turns no revenue into
     * cash. A curve with no receipts in it claiming to include them would have
     * PM-10 compare a founder's receipts-inclusive claim against a cost-only
     * model as though they were the same measure.
     */
    public function test_the_inclusions_are_read_off_the_ledger(): void
    {
        $costOnly = $this->keyed($this->forecast(['500', '-100'], []));
        self::assertFalse($costOnly['runway.includes_receipts:forecast_cash']->value);
        self::assertFalse($costOnly['runway.includes_owner_pay:forecast_cash']->value);

        $trading = $this->keyed($this->forecast(['500', '-100'], [
            new CashMovement('receipt', 'Terms of trade', '2026-01-31', '900', 'in', 'operating_receipt', 0),
            new CashMovement('pay', 'Pay schedule', '2026-02-07', '800', 'out', 'owner_pay', 1),
        ]));
        self::assertTrue($trading['runway.includes_receipts:forecast_cash']->value);
        self::assertTrue($trading['runway.includes_owner_pay:forecast_cash']->value);
        self::assertSame('operating_receipts_dated_in_the_horizon',
            $trading['runway.includes_receipts:forecast_cash']->basis);
    }

    /** A receipt dated outside the horizon is not in the curve the runway was
     * measured over, so it cannot be what the measure counts. */
    public function test_a_receipt_outside_the_horizon_is_not_counted_as_an_inclusion(): void
    {
        $facts = $this->keyed($this->forecast(['500', '-100'], [
            new CashMovement('receipt', 'Terms of trade', '2027-06-30', '900', 'in', 'operating_receipt', 0),
        ]));

        self::assertFalse($facts['runway.includes_receipts:forecast_cash']->value);
    }

    /**
     * The no-sales measure is the cash in hand over the policy's own monthly
     * cost basis, and it counts no receipts by construction: that is what the
     * measure is.
     */
    public function test_the_no_sales_runway_is_the_cash_over_the_policy_cost_basis(): void
    {
        $facts = $this->keyed($this->capture(['500'], [],
            new ReservePolicy('1000', 3, true, null, '0.10', 'Monthly fixed costs including owner pay'), '5000'));

        self::assertSame('5.00', $facts['model.runway_months:essential_cost_no_sales']->value);
        self::assertSame('months', $facts['model.runway_months:essential_cost_no_sales']->unit);
        self::assertFalse($facts['runway.includes_receipts:essential_cost_no_sales']->value);
        self::assertTrue($facts['runway.includes_owner_pay:essential_cost_no_sales']->value);
    }

    /** The same cash covers a different number of months depending on whether
     * the basis pays the owner, so the policy's own answer is what is
     * published rather than a reading of the figure. */
    public function test_the_no_sales_measure_carries_the_policy_answer_on_owner_pay(): void
    {
        $facts = $this->keyed($this->capture(['500'], [],
            new ReservePolicy('1000', 3, false, null, '0.10', 'Fixed costs excluding owner pay'), '5000'));

        self::assertFalse($facts['runway.includes_owner_pay:essential_cost_no_sales']->value);
    }

    /** An unanswered owner-pay question withholds the whole measure. PM-10
     * needs the figure and the statement together, and a figure nobody can
     * interpret is worse than no figure. */
    public function test_an_unanswered_owner_pay_question_withholds_the_no_sales_measure(): void
    {
        $facts = $this->capture(['500'], [], new ReservePolicy('1000', 3, null), '5000');

        self::assertSame([], array_values(array_filter(array_column($facts, 'entity'),
            fn (string $entity): bool => $entity === RunwayFacts::NO_SALES)));
    }

    /** No policy, no basis, or no opening cash each leave the measure unstated
     * rather than assuming one of them. */
    public function test_an_incomplete_policy_states_no_no_sales_runway(): void
    {
        $complete = new ReservePolicy('1000', 3, true, null, '0.10', 'Monthly fixed costs');

        self::assertSame([], $this->capture(['500'], [], null, '5000'));
        self::assertSame([], $this->capture(['500'], [], new ReservePolicy(null, 3, true), '5000'));
        self::assertSame([], $this->capture(['500'], [], $complete, null));
        self::assertSame([], $this->capture(['500'], [], new ReservePolicy('0', 3, true), '5000'),
            'Cash over a cost basis of nothing is not an infinite runway.');
    }

    /**
     * The two bases are stated separately and never reconciled against each
     * other. A business can have three months of forecast cash and five of
     * no-sales cover with nothing wrong: they answer different questions.
     */
    public function test_the_two_bases_are_stated_as_separate_records(): void
    {
        $facts = $this->keyed($this->capture(['500', '400', '100', '-200'], [],
            new ReservePolicy('1000', 3, true, null, '0.10', 'Monthly fixed costs'), '5000'));

        self::assertSame('3', $facts['model.runway_months:forecast_cash']->value);
        self::assertSame('5.00', $facts['model.runway_months:essential_cost_no_sales']->value);
        self::assertNotSame($facts['model.runway_months:forecast_cash']->basis,
            $facts['model.runway_months:essential_cost_no_sales']->basis,
            'Two measures sharing a basis label would read as one measure.');
    }

    public function test_every_fact_carries_the_record_it_came_from(): void
    {
        $facts = $this->capture(['500', '-100'], [],
            new ReservePolicy('1000', 3, true, null, '0.10', 'Monthly fixed costs'), '5000');

        self::assertNotSame([], $facts);
        foreach ($facts as $fact) {
            self::assertNotSame([], $fact->sources, $fact->key.' is asserted without the record behind it.');
        }
    }

    /**
     * @param  list<?string>  $closing
     * @param  list<CashMovement>  $movements
     * @return list<Fact>
     */
    private function forecast(array $closing, array $movements = []): array
    {
        return $this->capture($closing, $movements, null, null);
    }

    /**
     * @param  list<?string>  $closing  one month-end balance per forecast month
     * @param  list<CashMovement>  $movements
     * @return list<Fact>
     */
    private function capture(array $closing, array $movements, ?ReservePolicy $reserve, ?string $openingCash): array
    {
        $calendar = new ForecastCalendar('2026-01', count($closing));
        $months = [];
        foreach ($calendar->periods as $offset => $period) {
            $months[] = ['period' => $period, 'opening_cash' => null, 'receipts' => null,
                'payments' => null, 'closing_cash' => $closing[$offset]];
        }

        return (new RunwayFacts)->capture($calendar, $months, $movements, $reserve, $openingCash,
            [new SourceReference('captured_record', 'p1', 'forecast_model.drivers', str_repeat('c', 64))]);
    }

    /**
     * @param  list<Fact>  $facts
     * @return array<string,Fact>
     */
    private function keyed(array $facts): array
    {
        $keyed = [];
        foreach ($facts as $fact) {
            $keyed[$fact->key.':'.$fact->entity] = $fact;
        }

        return $keyed;
    }
}
