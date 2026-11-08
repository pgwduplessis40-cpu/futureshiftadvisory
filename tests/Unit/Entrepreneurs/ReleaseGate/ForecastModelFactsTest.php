<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\ActualBalances;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\ActualProfit;
use App\Services\Entrepreneurs\Forecast\ActualSource;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastEngineVersion;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\ForecastModelFacts;
use App\Services\Entrepreneurs\ReleaseGate\SourceRecords;
use App\Services\Entrepreneurs\ReleaseGate\SourceVersion;
use PHPUnit\Framework\TestCase;

final class ForecastModelFactsTest extends TestCase
{
    public function test_no_facts_are_produced_without_a_selected_reconstruction(): void
    {
        $source = new SourceRecords(new SourceVersion('p1', str_repeat('a', 64), str_repeat('b', 64), [], 0, '2026-03-15',
            CanonicalJson::encode(['plan_id' => 'p1', 'forecast_model' => null])));

        self::assertSame([], (new ForecastModelFacts)->capture($source));
    }

    public function test_a_reconstruction_the_engine_cannot_read_produces_no_facts(): void
    {
        $source = new SourceRecords(new SourceVersion('p1', str_repeat('a', 64), str_repeat('b', 64), [], 0, '2026-03-15',
            CanonicalJson::encode(['plan_id' => 'p1', 'forecast_model' => [
                'engine_version' => ForecastEngineVersion::CURRENT,
                // No calendar, which the codec refuses outright.
                'drivers' => ['streams' => []], 'actuals' => ['months' => []], 'reserve_policy' => null,
            ]])));

        self::assertSame([], (new ForecastModelFacts)->capture($source),
            'Partial facts from an unreadable model would put unverified figures in front of a rule.');
    }

    public function test_the_keys_the_enabled_rules_already_read_are_withheld(): void
    {
        $keys = array_column($this->facts(), 'key');

        foreach (ForecastModelFacts::WITHHELD as $withheld) {
            self::assertNotContains($withheld, $keys,
                $withheld.' is read by a rule that is already enabled, so emitting it here would move a verdict.');
        }
    }

    public function test_every_fact_carries_the_record_it_came_from(): void
    {
        $facts = $this->facts();

        self::assertNotSame([], $facts);
        foreach ($facts as $fact) {
            self::assertNotSame([], $fact->sources, $fact->key.' is asserted without the record behind it.');
        }
    }

    public function test_the_model_identity_and_driver_facts_are_produced_from_the_snapshot(): void
    {
        $facts = $this->keyed($this->facts());

        self::assertSame('2026-01-01', $facts['model.start_date:business:current']->value);
        self::assertSame('2026-01', $facts['model.start_period:business:current']->value);
        self::assertSame('2026-03-15', $facts['actuals.as_of:business:current']->value, 'The snapshot date is the release reading date.');
        // One stream in this model is unclassified, so there is no base total
        // to publish. The three-state flag recorded on the advisor screen
        // reaches the fact as an absent figure rather than as a total that
        // quietly leaves the unclassified stream out.
        self::assertNull($facts['model.base_revenue:business:month:1']->value);
        self::assertSame('120', $facts['capacity.available_hours:business:month:1']->value);
        self::assertSame('2', $facts['stream.units:workshops:month:1']->value);
        self::assertSame('founder', $facts['capacity.provider:workshops:current']->value);
    }

    public function test_an_unclassified_stream_emits_no_membership_rather_than_a_guess(): void
    {
        $facts = $this->keyed($this->facts());

        self::assertTrue($facts['stream.membership:workshops:current']->value);
        self::assertTrue($facts['stream.membership:workshops:current']->confirmed);
        self::assertArrayNotHasKey('stream.membership:retreats:current', $facts,
            'An absent flag reported as missing is the finding; a flag invented here would be the defect.');
    }

    public function test_a_reconciled_month_is_labelled_actual_and_the_rest_forecast(): void
    {
        $facts = $this->keyed($this->facts());

        self::assertSame('reconciled', $facts['actuals.status:business:month:1']->value);
        self::assertTrue($facts['actuals.status:business:month:1']->confirmed);
        self::assertSame('actual', $facts['period.label:business:month:1']->value);
        self::assertSame('5000.00', $facts['actuals.closing_cash:business:month:1']->value);
        // The month after the reviewed one is where the forecast resumes.
        self::assertSame('forecast', $facts['period.label:business:month:2']->value);
        self::assertArrayHasKey('forecast.opening_cash:business:month:2', $facts);
    }

    /** @return list<Fact> */
    private function facts(): array
    {
        $inputs = new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 3),
            streams: [
                new RevenueStream('workshops', 'Weekend workshops', 'workshop', '450', '4', '6', true, 'own_record', [
                    '2026-01' => new StreamMonth('2', '2', '2', '20', '0'),
                ]),
                // Classified by nobody, which must stay that way.
                new RevenueStream('retreats', 'Retreats', 'retreat', '1000', '4', '6', null, null, []),
            ],
            founderMonths: ['2026-01' => new FounderMonth('120', '20', '15')],
            cashOpening: new CashOpening('5000', 'Bank statement to 31 December 2025', true),
            actualMonths: [$this->actual()],
        );
        $balances = fn (?string $cash): ActualBalances => new ActualBalances($cash, null, null, null, null, null);

        $records = ['plan_id' => 'p1', 'forecast_model' => [
            'engine_version' => ForecastEngineVersion::CURRENT,
            'drivers' => json_decode(CanonicalJson::encode($inputs->driversPayload()), true),
            'actuals' => json_decode(CanonicalJson::encode($inputs->actualsPayload()), true),
            'reserve_policy' => null,
        ]];

        return (new ForecastModelFacts)->capture(new SourceRecords(
            new SourceVersion('p1', str_repeat('a', 64), str_repeat('b', 64), [], 0, '2026-03-15', CanonicalJson::encode($records)),
        ));
    }

    private function actual(): ActualMonth
    {
        $balances = fn (?string $cash): ActualBalances => new ActualBalances($cash, null, null, null, null, null);

        return new ActualMonth(
            period: '2026-01', state: 'trading',
            source: new ActualSource(null, 'manual', null, null, null, null, null, 'NZD', null, 'A. Advisor', '2026-02-05', null, true),
            profit: new ActualProfit(null, null, null, null, null, null, null),
            opening: $balances(null), closing: $balances('5000.00'),
            movements: null, movementAccounts: null, closingObligations: null, preTradingEvidence: null,
        );
    }

    /**
     * @param  list<Fact>  $facts
     * @return array<string,Fact>
     */
    private function keyed(array $facts): array
    {
        $keyed = [];
        foreach ($facts as $fact) {
            $keyed[$fact->key.':'.$fact->entity.':'.$fact->period] = $fact;
        }

        return $keyed;
    }
}
