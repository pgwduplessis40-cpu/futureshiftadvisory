<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\ActualsRebase;
use App\Services\Entrepreneurs\Forecast\ActualsWindow;
use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\FixedCost;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\FundingSource;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\ScenarioApplication;
use App\Services\Entrepreneurs\Forecast\ScenarioCatalogue;
use App\Services\Entrepreneurs\Forecast\ScenarioDefinition;
use App\Services\Entrepreneurs\Forecast\ScenarioRunner;
use App\Services\Entrepreneurs\Forecast\ScenarioShock;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\TradeItem;
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
        $definitions = [
            new ScenarioDefinition('base', 'Base', [], 'test'),
            new ScenarioDefinition('collections_delay', 'Collections delay', [
                new ScenarioShock(ScenarioShock::TYPE_COLLECTION_DELAY_DAYS, '30', ['operating_receipt']),
            ], 'test'),
        ];

        $result = (new ScenarioRunner)->run(
            $this->inputs($calendar, tradeItems: [
                $this->receivable('receipt', '2027-01-05', '500'),
                $this->payable('spend', '2027-01-20', '400'),
            ]),
            $this->openWindow($calendar),
            $definitions,
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
        self::assertNull($result['refusal']);
    }

    /**
     * The defect this slice exists to fix.
     *
     * A cost-rate shock scales the rate behind each overhead, the recurrence
     * raises larger invoices from it, and the ledger pays them. Before the
     * shock reached the inputs it touched only collection dates, so every
     * scenario but a delay one reported the base case's own cash deficit while
     * claiming to be a downside.
     */
    public function test_a_cost_rate_shock_raises_the_overhead_payments_in_the_ledger(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $result = (new ScenarioRunner)->run(
            $this->inputs($calendar, fixedCosts: [$this->rent('1000')]),
            $this->openWindow($calendar),
            [
                new ScenarioDefinition('base', 'Base', [], 'test'),
                new ScenarioDefinition('cost_upside', 'Cost upside', [
                    new ScenarioShock(ScenarioShock::TYPE_COST_RATE_FACTOR, '1.1', ['fixed_cost']),
                ], 'test'),
            ],
        );

        self::assertSame('pass', $result['status']);

        // Opening 100 against two monthly invoices of 1000: the trough is
        // 100 - 1000 - 1000. At a 1.1 rate factor each invoice is 1100.
        self::assertSame('1900.00', $result['outcomes'][0]->funding['cash_deficit']);
        self::assertSame('2100.00', $result['outcomes'][1]->funding['cash_deficit']);

        self::assertNotSame(
            $result['outcomes'][0]->funding['cash_deficit'],
            $result['outcomes'][1]->funding['cash_deficit'],
            'a cost upside that reports the base case deficit has not been applied',
        );
        self::assertSame('cost_upside', $result['worst']['scenario_id']);
    }

    /** An asset's cost carries the payment its schedule dates, so a capex
     * shock reaches cash through that payment rather than stopping at the
     * depreciation charge.
     */
    public function test_a_capex_shock_raises_the_capital_payment_in_the_ledger(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $result = (new ScenarioRunner)->run(
            $this->inputs($calendar, assets: [$this->asset('1000')]),
            $this->openWindow($calendar),
            [
                new ScenarioDefinition('base', 'Base', [], 'test'),
                new ScenarioDefinition('cost_upside', 'Cost upside', [
                    new ScenarioShock(ScenarioShock::TYPE_COST_RATE_FACTOR, '1.1', ['capex']),
                ], 'test'),
            ],
        );

        self::assertSame('900.00', $result['outcomes'][0]->funding['cash_deficit']);
        self::assertSame('1000.00', $result['outcomes'][1]->funding['cash_deficit']);
    }

    /** The base scenario is the reconstruction, not a copy of it. Its ledger is
     * therefore the main model's ledger by construction, which is the
     * reconciliation SS-01 exists to check rather than an agreement two
     * separate computations happen to reach.
     */
    public function test_the_base_scenario_runs_the_reconstruction_itself(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $inputs = $this->inputs($calendar, fixedCosts: [$this->rent('1000')]);

        $shocked = (new ScenarioApplication)->shockInputs(
            $this->openWindow($calendar), $inputs, new ScenarioDefinition('base', 'Base', [], 'test')
        );

        self::assertSame($inputs, $shocked);
    }

    /** A rate is one arrangement-level figure with no month of its own, so a
     * shock scoped to named months cannot be expressed on it. Saying so beats
     * applying it across the whole horizon or dropping the scope in silence.
     */
    public function test_a_cost_rate_shock_scoped_to_named_months_is_refused(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $result = (new ScenarioRunner)->run(
            $this->inputs($calendar, fixedCosts: [$this->rent('1000')]),
            $this->openWindow($calendar),
            [new ScenarioDefinition('cost_upside', 'Cost upside', [
                new ScenarioShock(ScenarioShock::TYPE_COST_RATE_FACTOR, '1.1', ['fixed_cost'], ['2027-01']),
            ], 'test')],
        );

        self::assertSame('fail', $result['status']);
        self::assertSame([], $result['outcomes']);
        self::assertNotNull($result['refusal']);
        self::assertStringContainsString('cannot be scoped to named months', $result['refusal']);
        self::assertNull($result['worst']['scenario_id']);
    }

    /** A ledger standing on an input nobody has priced does not get to publish
     * a cash deficit. The scenario is listed as unranked instead, because a
     * figure missing part of what it was built from is not a mild case.
     */
    public function test_a_scenario_built_on_an_unpriced_input_publishes_no_deficit(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $unpriced = new FixedCost('rent', 'lease', null, '1', 'monthly', '2027-01-10', '2027-02-10', 0, true);

        $result = (new ScenarioRunner)->run(
            $this->inputs($calendar, fixedCosts: [$unpriced]),
            $this->openWindow($calendar),
            [new ScenarioDefinition('base', 'Base', [], 'test')],
        );

        self::assertSame('cannot_verify', $result['status']);
        self::assertNull($result['outcomes'][0]->funding['cash_deficit'], 'an unpriced overhead cannot produce a deficit');
        self::assertContains('rent.rate', $result['outcomes'][0]->funding['missing']);
        self::assertSame(['base'], $result['unranked']);
    }

    /** A downside that improves an early cash minimum is reported as computed,
     * never adjusted to force a worse result than the base case.
     */
    public function test_a_downside_that_improves_early_cash_is_not_forced_to_look_worse(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $definitions = [
            new ScenarioDefinition('base', 'Base', [], 'test'),
            new ScenarioDefinition('supplier_relief', 'Supplier relief', [
                new ScenarioShock(ScenarioShock::TYPE_COLLECTION_DELAY_DAYS, '30', ['operating_receipt']),
            ], 'test'),
        ];

        $result = (new ScenarioRunner)->run(
            $this->inputs($calendar, opening: '5000', tradeItems: [$this->receivable('receipt', '2027-01-05', '500')]),
            $this->openWindow($calendar),
            $definitions,
        );

        self::assertSame('0.00', $result['outcomes'][0]->funding['cash_deficit']);
        self::assertSame('0.00', $result['outcomes'][1]->funding['cash_deficit']);
        self::assertSame('5000.00', $result['outcomes'][1]->funding['lowest_cash'], 'the trough is reported as modelled');
    }

    public function test_an_unrankable_scenario_is_listed_rather_than_ranked_mildest(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);

        $result = (new ScenarioRunner)->run(
            $this->inputs($calendar, fundingSources: [
                new FundingSource('hoped', 'investor', 'Angel', 'equity', '500', 'indicative', null, '2027-01-05', 'unrestricted', null),
            ]),
            $this->openWindow($calendar),
            [new ScenarioDefinition('base', 'Base', [], 'test')],
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
            new ForecastModelInputs(
                calendar: $calendar,
                streams: [],
                founderMonths: [],
                cashOpening: new CashOpening('7531', 'reviewed bank statement', true),
                tradeItems: [$this->payable('fixed', '2027-03-20', '4335.07')],
                reservePolicy: new ReservePolicy('4335.07', 3, true, null, '0.10', 'three months of fixed costs including owner pay'),
            ),
            $window,
            $definitions,
        );

        self::assertCount(6, $result['outcomes']);
        self::assertSame('pass', $result['status']);
        self::assertSame('0.00', $result['maxima']['cash_deficit']['value'], 'opening cash covers the payment');
        self::assertNotNull($result['maxima']['reserve_shortfall']['value']);
    }

    /**
     * @param  list<TradeItem>  $tradeItems
     * @param  list<FixedCost>  $fixedCosts
     * @param  list<AssetInput>  $assets
     * @param  list<FundingSource>  $fundingSources
     */
    private function inputs(
        ForecastCalendar $calendar,
        string $opening = '100',
        array $tradeItems = [],
        array $fixedCosts = [],
        array $assets = [],
        array $fundingSources = [],
    ): ForecastModelInputs {
        return new ForecastModelInputs(
            calendar: $calendar,
            streams: [$this->stream('10')],
            founderMonths: $this->founderMonths($calendar),
            cashOpening: new CashOpening($opening, 'reviewed bank statement', true),
            tradeItems: $tradeItems,
            assets: $assets,
            reservePolicy: new ReservePolicy('1000', 3, false, null, '0.10', 'three months of fixed costs excluding owner pay'),
            fundingSources: $fundingSources,
            fixedCosts: $fixedCosts,
        );
    }

    private function receivable(string $id, string $date, string $amount): TradeItem
    {
        return new TradeItem($id, 'plan', 'receivable', $date, $amount,
            [new CashMovement($id.'.balance', 'plan', $date, $amount, 'in', 'operating_receipt', 0)]);
    }

    private function payable(string $id, string $date, string $amount): TradeItem
    {
        return new TradeItem($id, 'plan', 'payable', $date, $amount,
            [new CashMovement($id.'.payment', 'plan', $date, $amount, 'out', 'operating_payment', 1)]);
    }

    private function rent(string $rate): FixedCost
    {
        return new FixedCost('rent', 'lease', $rate, '1', 'monthly', '2027-01-10', '2027-02-10', 0, true);
    }

    private function asset(string $cost): AssetInput
    {
        return new AssetInput('kit', 'invoice', $cost, 'capital', 'policy', true, '2027-01-15', '2027-01-01', 12, '0',
            'straight_line_full_start_month');
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
