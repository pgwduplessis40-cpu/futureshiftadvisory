<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\ActualsRebase;
use App\Services\Entrepreneurs\Forecast\ActualsWindow;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\ScenarioApplication;
use App\Services\Entrepreneurs\Forecast\ScenarioCatalogue;
use App\Services\Entrepreneurs\Forecast\ScenarioDefinition;
use App\Services\Entrepreneurs\Forecast\ScenarioRunner;
use App\Services\Entrepreneurs\Forecast\ScenarioShock;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Entrepreneurs\Forecast\ActualsFixture as F;

final class ScenarioRunnerTest extends TestCase
{
    public function test_requested_and_effective_shock_are_reported_separately(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $window = $this->openWindow($calendar);
        $definition = new ScenarioDefinition('revenue_downside', 'Revenue downside', [
            new ScenarioShock(ScenarioShock::TYPE_VOLUME_FACTOR, '0.8', ['intensive']),
        ], 'test');

        $report = (new ScenarioApplication)->revenueShockReport(
            $calendar, $window, [$this->stream('10')], $this->founderMonths($calendar), $definition
        );

        self::assertNotNull($report);
        self::assertSame('0.8', $report->requestedFactor);
        self::assertSame('30000.00', $report->baselineAmount);
        self::assertSame('24000.00', $report->shockedAmount);
        self::assertSame('0.800000', $report->effectiveFactor());
        self::assertFalse($report->divergedFromRequest());
        self::assertContains('capacity limits and pricing are applied after the volume shock', $report->reasons);
    }

    /** Capacity binds before the shock does, so cutting requested volume by a
     * fifth cuts delivered revenue by less. Both numbers are reported.
     */
    public function test_capacity_limits_make_the_effective_shock_milder_than_requested(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $window = $this->openWindow($calendar);
        $definition = new ScenarioDefinition('revenue_downside', 'Revenue downside', [
            new ScenarioShock(ScenarioShock::TYPE_VOLUME_FACTOR, '0.8', ['intensive']),
        ], 'test');

        // 20 requested units against 10 units of founder capacity: the base
        // case already delivers 10, and 16 requested still delivers 10.
        $report = (new ScenarioApplication)->revenueShockReport(
            $calendar, $window, [$this->stream('20')], $this->founderMonths($calendar), $definition
        );

        self::assertNotNull($report);
        self::assertSame($report->baselineAmount, $report->shockedAmount);
        self::assertSame('1.000000', $report->effectiveFactor());
        self::assertTrue($report->divergedFromRequest(), 'a 0.8 request that changes nothing must say so');
    }

    public function test_the_base_case_has_no_revenue_shock_report(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $base = new ScenarioDefinition('base', 'Base', [], 'test');

        self::assertNull((new ScenarioApplication)->revenueShockReport(
            $calendar, $this->openWindow($calendar), [$this->stream('10')], $this->founderMonths($calendar), $base
        ));
    }

    public function test_the_runner_ranks_the_worst_scenario_by_its_named_metric_and_records_maxima(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $window = $this->openWindow($calendar);
        $definitions = [
            new ScenarioDefinition('base', 'Base', [], 'test'),
            new ScenarioDefinition('collections_delay', 'Collections delay', [
                new ScenarioShock(ScenarioShock::TYPE_COLLECTION_DELAY_DAYS, '30', ['operating_receipt']),
            ], 'test'),
        ];

        $result = (new ScenarioRunner)->run(
            $calendar,
            $window,
            $definitions,
            [$this->stream('10')],
            $this->founderMonths($calendar),
            new CashOpening('100', 'reviewed bank statement', true),
            [
                new CashMovement('receipt', 'plan', '2027-01-05', '500', 'in', 'operating_receipt', 0),
                new CashMovement('spend', 'plan', '2027-01-20', '400', 'out', 'operating_payment', 1),
            ],
            new ReservePolicy('1000', 3, false, null, '0.10', 'three months of fixed costs excluding owner pay'),
        );

        self::assertSame('pass', $result['status']);
        self::assertCount(2, $result['outcomes']);

        // Base: 100 + 500 - 400 never dips below zero, so no deficit.
        // Delayed: the 400 payment lands before the receipt moves to February.
        self::assertSame('0.00', $result['outcomes'][0]->funding['cash_deficit']);
        self::assertSame('300.00', $result['outcomes'][1]->funding['cash_deficit']);

        self::assertSame('collections_delay', $result['worst']['scenario_id']);
        self::assertSame(ScenarioDefinition::RANK_CASH_DEFICIT, $result['worst']['ranking_metric']);
        self::assertSame('300.00', $result['worst']['value']);

        self::assertSame('300.00', $result['maxima']['cash_deficit']['value']);
        self::assertSame('collections_delay', $result['maxima']['cash_deficit']['scenario_id']);
        self::assertSame('2027-01-20', $result['maxima']['cash_deficit']['date']);
        self::assertSame([], $result['unranked']);
    }

    /** A downside that improves an early cash minimum is reported as computed,
     * never adjusted to force a worse result than the base case.
     */
    public function test_a_downside_that_improves_early_cash_is_not_forced_to_look_worse(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $window = $this->openWindow($calendar);
        $definitions = [
            new ScenarioDefinition('base', 'Base', [], 'test'),
            new ScenarioDefinition('supplier_relief', 'Supplier relief', [
                new ScenarioShock(ScenarioShock::TYPE_COLLECTION_DELAY_DAYS, '30', ['operating_receipt']),
            ], 'test'),
        ];

        $result = (new ScenarioRunner)->run(
            $calendar, $window, $definitions, [$this->stream('10')], $this->founderMonths($calendar),
            new CashOpening('5000', 'reviewed bank statement', true),
            [new CashMovement('receipt', 'plan', '2027-01-05', '500', 'in', 'operating_receipt', 0)],
            new ReservePolicy('1000', 3, false, null, '0.10', 'three months of fixed costs excluding owner pay'),
        );

        self::assertSame('0.00', $result['outcomes'][0]->funding['cash_deficit']);
        self::assertSame('0.00', $result['outcomes'][1]->funding['cash_deficit']);
        self::assertSame('5000.00', $result['outcomes'][1]->funding['lowest_cash'], 'the trough is reported as modelled');
    }

    public function test_an_unrankable_scenario_is_listed_rather_than_ranked_mildest(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $window = $this->openWindow($calendar);

        $result = (new ScenarioRunner)->run(
            $calendar, $window,
            [new ScenarioDefinition('base', 'Base', [], 'test')],
            [$this->stream('10')],
            $this->founderMonths($calendar),
            new CashOpening('100', 'reviewed bank statement', true),
            [new CashMovement('hoped', 'investor', '2027-01-05', '500', 'in', 'funding', 0)],
            new ReservePolicy('1000', 3, false, null, '0.10', 'three months of fixed costs excluding owner pay'),
        );

        self::assertSame('cannot_verify', $result['status']);
        self::assertSame(['base'], $result['unranked']);
        self::assertNull($result['worst']['scenario_id'], 'an unknown is not the mildest case');
        self::assertNull($result['maxima']['cash_deficit']['value']);
    }

    public function test_the_required_catalogue_runs_end_to_end(): void
    {
        $calendar = new ForecastCalendar('2027-01', 4);
        $window = (new ActualsRebase)->calculate($calendar, '2027-03-01', 'synthetic-business', F::twoMonths());
        $definitions = ScenarioCatalogue::required($window, ['intensive'], ['operating_receipt']);

        $result = (new ScenarioRunner)->run(
            $calendar, $window, $definitions, [], [],
            new CashOpening('7531', 'reviewed bank statement', true),
            [new CashMovement('fixed', 'plan', '2027-03-20', '4335.07', 'out', 'operating_payment', 0)],
            new ReservePolicy('4335.07', 3, true, null, '0.10', 'three months of fixed costs including owner pay'),
        );

        self::assertCount(6, $result['outcomes']);
        self::assertSame('pass', $result['status']);
        self::assertSame('0.00', $result['maxima']['cash_deficit']['value'], 'opening cash covers the payment');
        self::assertNotNull($result['maxima']['reserve_shortfall']['value']);
    }

    private function openWindow(ForecastCalendar $calendar): ActualsWindow
    {
        return new ActualsWindow('not_applicable', $calendar->startMonth.'-01', null, [], $calendar->periods, null, null, '', [], []);
    }

    private function stream(string $units): RevenueStream
    {
        $schedule = [];
        foreach (['2027-01', '2027-02'] as $period) {
            $schedule[$period] = new StreamMonth($units, $units, $units, '60', '0');
        }

        return new RevenueStream('intensive', 'Intensive', 'day', '1500', '0', '6', true, 'founder_stated', $schedule);
    }

    /** @return array<string,FounderMonth> */
    private function founderMonths(ForecastCalendar $calendar): array
    {
        $months = [];
        foreach ($calendar->periods as $period) {
            $months[$period] = new FounderMonth('60', '0', '0');
        }

        return $months;
    }
}
