<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\ActualsRebase;
use App\Services\Entrepreneurs\Forecast\ActualsWindow;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\ScenarioApplication;
use App\Services\Entrepreneurs\Forecast\ScenarioCatalogue;
use App\Services\Entrepreneurs\Forecast\ScenarioDefinition;
use App\Services\Entrepreneurs\Forecast\ScenarioShock;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Entrepreneurs\Forecast\ActualsFixture as F;

final class ScenarioApplicationTest extends TestCase
{
    public function test_required_catalogue_names_its_defaults_and_bounds_slow_start_to_the_forecast_window(): void
    {
        $window = $this->window();
        $scenarios = ScenarioCatalogue::required($window, ['intensive'], ['operating_receipt']);

        self::assertSame(
            [ScenarioCatalogue::BASE, ScenarioCatalogue::REVENUE_DOWNSIDE, ScenarioCatalogue::SLOW_START,
                ScenarioCatalogue::COST_UPSIDE, ScenarioCatalogue::COLLECTIONS_DELAY, ScenarioCatalogue::COMBINED_DOWNSIDE],
            array_map(static fn (ScenarioDefinition $d): string => $d->id, $scenarios),
        );
        self::assertTrue($scenarios[0]->isBaseCase());
        foreach ($scenarios as $definition) {
            self::assertNotSame('', $definition->defaultsSource);
            self::assertSame(ScenarioDefinition::RANK_CASH_DEFICIT, $definition->rankingMetric);
        }

        $slowStart = $scenarios[2]->shocksOfType(ScenarioShock::TYPE_VOLUME_FACTOR)[0];
        self::assertSame($window->forecastPeriods, $slowStart->periods);
        self::assertStringContainsString('after the actuals cutoff', (string) $slowStart->basis);

        $combined = $scenarios[5];
        self::assertCount(1, $combined->shocksOfType(ScenarioShock::TYPE_VOLUME_FACTOR));
        self::assertCount(1, $combined->shocksOfType(ScenarioShock::TYPE_COST_RATE_FACTOR));
        self::assertCount(1, $combined->shocksOfType(ScenarioShock::TYPE_COLLECTION_DELAY_DAYS));
    }

    public function test_volume_shock_scales_booked_delivered_and_invoiced_units_together(): void
    {
        $window = $this->window();
        $definition = ScenarioCatalogue::required($window, ['intensive'], ['operating_receipt'])[1];

        $shocked = (new ScenarioApplication)->shockStreams($window, [$this->stream()], $definition);
        $month = $shocked[0]->schedule['2027-03'];

        self::assertSame('8', $month->bookedUnits);
        self::assertSame('8', $month->deliveryUnits);
        self::assertSame('8', $month->invoicedUnits);
        self::assertSame('80', $month->founderHours, 'capacity is an input, not a shock target');
    }

    public function test_an_unknown_unit_count_stays_unknown_rather_than_becoming_zero(): void
    {
        $window = $this->window();
        $definition = ScenarioCatalogue::required($window, ['intensive'], ['operating_receipt'])[1];
        $stream = new RevenueStream('intensive', 'Intensive', 'day', '1500', '2', '6', true, 'founder_stated', [
            '2027-03' => new StreamMonth(null, null, null, '80', null),
        ]);

        $month = (new ScenarioApplication)->shockStreams($window, [$stream], $definition)[0]->schedule['2027-03'];

        self::assertNull($month->deliveryUnits);
    }

    public function test_an_explicit_shock_on_an_elapsed_actual_month_is_rejected(): void
    {
        $window = $this->window();
        $definition = new ScenarioDefinition('manual_downside', 'Manual downside', [
            new ScenarioShock(ScenarioShock::TYPE_VOLUME_FACTOR, '0.8', ['intensive'], ['2027-01']),
        ], 'test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Actual or out-of-calendar periods cannot be changed by a forecast scenario.');

        (new ScenarioApplication)->shockStreams($window, [$this->stream()], $definition);
    }

    public function test_an_unbounded_shock_leaves_actual_months_untouched_instead_of_failing(): void
    {
        $window = $this->window();
        $definition = ScenarioCatalogue::required($window, ['intensive'], ['operating_receipt'])[1];
        $stream = new RevenueStream('intensive', 'Intensive', 'day', '1500', '2', '6', true, 'founder_stated', [
            '2027-01' => new StreamMonth('4', '4', '4', '80', null),
            '2027-03' => new StreamMonth('10', '10', '10', '80', null),
        ]);

        $schedule = (new ScenarioApplication)->shockStreams($window, [$stream], $definition)[0]->schedule;

        self::assertSame('4', $schedule['2027-01']->deliveryUnits, 'history is fixed in every scenario');
        self::assertSame('8', $schedule['2027-03']->deliveryUnits);
    }

    public function test_cost_upside_scales_only_declared_categories_and_never_tax_or_financing(): void
    {
        $window = $this->window();
        $definition = ScenarioCatalogue::required($window, ['intensive'], ['operating_receipt'])[3];

        $rates = (new ScenarioApplication)->shockCostRates($window, [
            'direct_cost' => '100.00',
            'fixed_cost' => '4335.07',
            'capex' => '2000.00',
            'tax' => '1838.18',
            'funding' => '6652.00',
        ], $definition);

        self::assertSame('110.00', $rates['direct_cost']);
        self::assertSame('4768.58', $rates['fixed_cost']);
        self::assertSame('2200.00', $rates['capex']);
        self::assertSame('1838.18', $rates['tax'], 'tax is recomputed as a response, never multiplied');
        self::assertSame('6652.00', $rates['funding'], 'financing receipts are not a cost rate');
    }

    public function test_a_cost_shock_cannot_be_declared_against_tax_or_financing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cost-rate shocks apply only to direct cost, fixed cost or capex categories.');

        new ScenarioShock(ScenarioShock::TYPE_COST_RATE_FACTOR, '1.1', ['tax']);
    }

    public function test_collections_delay_moves_receipts_later_and_leaves_payments_alone(): void
    {
        $window = $this->window();
        $definition = ScenarioCatalogue::required($window, ['intensive'], ['operating_receipt', 'operating_payment'])[4];

        $moved = (new ScenarioApplication)->shockCollections($window, [
            new CashMovement('receipt', 'plan', '2027-03-10', '3000', 'in', 'operating_receipt', 0),
            new CashMovement('payment', 'plan', '2027-03-20', '1200', 'out', 'operating_payment', 1),
            new CashMovement('historic', 'actuals', '2027-01-15', '900', 'in', 'operating_receipt', 2),
        ], $definition);

        self::assertSame('2027-04-09', $moved[0]->date);
        self::assertSame('2027-03-20', $moved[1]->date, 'delaying a payment would improve cash and invert the shock');
        self::assertSame('2027-01-15', $moved[2]->date, 'received cash is already in the opening balance');
    }

    public function test_a_shock_without_eligible_targets_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A scenario shock must state its eligible targets.');

        new ScenarioShock(ScenarioShock::TYPE_VOLUME_FACTOR, '0.8', []);
    }

    public function test_base_case_returns_its_inputs_unchanged(): void
    {
        $window = $this->window();
        $base = ScenarioCatalogue::required($window, ['intensive'], ['operating_receipt'])[0];
        $streams = [$this->stream()];

        self::assertSame($streams, (new ScenarioApplication)->shockStreams($window, $streams, $base));
    }

    private function window(): ActualsWindow
    {
        return (new ActualsRebase)->calculate(new ForecastCalendar('2027-01', 4), '2027-03-01', 'synthetic-business', F::twoMonths());
    }

    private function stream(): RevenueStream
    {
        return new RevenueStream('intensive', 'Intensive', 'day', '1500', '2', '6', true, 'founder_stated', [
            '2027-03' => new StreamMonth('10', '10', '10', '80', null),
        ]);
    }
}
