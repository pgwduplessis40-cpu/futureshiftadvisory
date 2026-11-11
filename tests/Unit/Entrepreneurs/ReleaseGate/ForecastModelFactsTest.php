<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\ActualBalances;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\ActualProfit;
use App\Services\Entrepreneurs\Forecast\ActualSource;
use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\FundingSource;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastEngineVersion;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\TradeItem;
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

    public function test_the_dated_cash_curve_and_its_ledger_come_from_the_reconstruction(): void
    {
        $facts = $this->keyed($this->capture($this->cashModel()));

        // 5000 opening, 900 received and 2400 paid in February, nothing else.
        self::assertSame(['5000.00', '0.00', '5000.00'], [$facts['cash.opening:business:month:1']->value,
            $facts['cash.movement:business:month:1']->value, $facts['cash.closing:business:month:1']->value]);
        self::assertSame(['5000.00', '-1500.00', '3500.00'], [$facts['cash.opening:business:month:2']->value,
            $facts['cash.movement:business:month:2']->value, $facts['cash.closing:business:month:2']->value]);
        self::assertSame('3500.00', $facts['cash.opening:business:month:3']->value, 'Each month opens on the balance before it closed.');
        self::assertSame('2026-02-01', $facts['cash.period_start:business:month:2']->value);
        self::assertSame('complete', $facts['ledger.coverage:business:month:2']->value);

        // The dated and classified lines behind the month, which is evidence
        // nothing on the platform produced before this.
        self::assertSame('900', $facts['ledger.amount:invoice-1.payment:month:2']->value);
        self::assertSame('2026-02-10', $facts['ledger.date:invoice-1.payment:month:2']->value);
        self::assertSame('customer_receipts', $facts['ledger.category:invoice-1.payment:month:2']->value);
        self::assertSame('capex', $facts['ledger.category:laptop.purchase:month:2']->value,
            'A capital asset leaves cash as capital expenditure, not as an operating payment.');
    }

    public function test_the_opening_position_is_dated_where_the_forecast_opens_and_claims_no_restriction(): void
    {
        $opening = $this->keyed($this->capture($this->cashModel()))['model.opening_cash:business:current'];

        self::assertSame(['5000', 'NZD', '2026-01-01', true], [$opening->value, $opening->unit, $opening->asOf, $opening->confirmed]);
        self::assertNull($opening->basis, 'The reconstruction records no account or restriction scope, so none is claimed.');
    }

    public function test_a_ledger_line_stays_identifiable_when_one_record_settles_in_instalments(): void
    {
        $facts = $this->keyed($this->capture($this->cashModel(tradeItems: [
            new TradeItem('invoice-1', 'Sales ledger export', 'receivable', '2026-01-05', '900', [
                new CashMovement('invoice-1.deposit', 'Bank statement', '2026-02-10', '300', 'in', 'operating_receipt', 0, true),
                new CashMovement('invoice-1.balance', 'Bank statement', '2026-02-24', '600', 'in', 'operating_receipt', 1, true),
            ]),
        ])));

        // The rule requires each ledger line to be identifiable once. Two
        // instalments of one invoice share a record, so the record on its own
        // would read as the same line counted twice.
        self::assertNotSame(
            $facts['ledger.source:invoice-1.deposit:month:2']->value,
            $facts['ledger.source:invoice-1.balance:month:2']->value,
            'Two instalments are two ledger lines, not one line counted twice.',
        );
        self::assertStringContainsString('Bank statement', $facts['ledger.source:invoice-1.deposit:month:2']->value);
    }

    public function test_a_movement_the_gate_has_no_category_for_is_reported_rather_than_filed_under_one(): void
    {
        $facts = $this->keyed($this->capture($this->cashModel(tradeItems: [
            // A GST refund. It is a receipt, but the rule's inflow categories
            // are customer receipts, other operating receipts and financing
            // inflows, and none of them describes a refund of GST without
            // asserting something about it that nobody recorded.
            new TradeItem('gst-q1', 'IRD assessment', 'receivable', '2026-01-31', '1200', [
                new CashMovement('gst-q1.refund', 'IRD statement', '2026-02-20', '1200', 'in', 'gst', 0, true),
            ]),
        ])));

        self::assertSame('1200', $facts['ledger.amount:gst-q1.refund:month:2']->value, 'The entry is still evidence.');
        self::assertArrayNotHasKey('ledger.category:gst-q1.refund:month:2', $facts,
            'An unclassifiable movement reported as such is the finding; a category invented here would be the defect.');
    }

    public function test_a_movement_the_ledger_cannot_date_withholds_the_balances_and_enters_no_month(): void
    {
        $facts = $this->keyed($this->capture($this->cashModel(tradeItems: [
            new TradeItem('invoice-2', 'Sales ledger export', 'receivable', '2026-01-05', '900', [
                new CashMovement('invoice-2.payment', 'Bank statement', null, '900', 'in', 'operating_receipt', 0, true),
            ]),
        ])));

        // An undated movement can fall in any month, so the ledger withholds
        // every balance rather than placing it. Nothing is hidden by leaving
        // it out of the entries: the months report themselves unverifiable.
        self::assertNull($facts['cash.opening:business:month:1']->value);
        self::assertNull($facts['cash.movement:business:month:1']->value);
        self::assertSame('incomplete', $facts['ledger.coverage:business:month:1']->value);
        self::assertArrayNotHasKey('ledger.amount:invoice-2.payment:month:1', $facts);
    }

    public function test_an_annual_total_is_published_only_for_a_year_the_reconstruction_finishes(): void
    {
        $short = $this->keyed($this->capture($this->cashModel()));
        self::assertArrayNotHasKey('cash.annual_movement:business:year:1', $short,
            'A three-month reconstruction has no annual total, and a sum of three months is not one.');

        $full = $this->keyed($this->capture($this->cashModel(months: 12)));
        self::assertSame('-1500.00', $full['cash.annual_movement:business:year:1']->value);
        self::assertSame('3500.00', $full['cash.annual_closing:business:year:1']->value);
    }

    public function test_every_key_taken_over_from_the_budget_is_either_replaced_or_declared_unstated(): void
    {
        // A full year with a complete funding inventory, so every key a
        // finished reconstruction can state is reachable and an absent one is
        // a real gap rather than a short or half-filled model.
        $keys = array_unique(array_column($this->capture($this->cashModel(months: 12, funded: true)), 'key'));

        self::assertSame([], array_values(array_diff(ForecastModelFacts::SUPERSEDES, $keys, ForecastModelFacts::WITHHELD)),
            'A key taken off the budget has to be replaced here or declared as one this model cannot state.');
        self::assertSame([],
            array_values(array_intersect(ForecastModelFacts::SUPERSEDES, ForecastModelFacts::WITHHELD)),
            'Taking a figure off the budget and leaving it unstated is the exception, so it stays a short and named list.');
    }

    /** The one key that used to be taken off the budget and left unstated.
     *
     * It is stated from the base cash curve rather than from the scenario run,
     * so it is replaced on every reconstruction that closes its months - not
     * only on those whose scenarios can be established.
     */
    public function test_the_base_trough_is_the_lowest_month_end_closing_balance(): void
    {
        $facts = $this->keyed($this->capture($this->cashModel(months: 12)));

        $closings = [];
        foreach ($this->capture($this->cashModel(months: 12)) as $fact) {
            if ($fact->key === 'cash.closing' && $fact->scenario === 'base' && $fact->value !== null) {
                $closings[] = (float) $fact->value;
            }
        }

        self::assertNotSame([], $closings);
        self::assertSame(min($closings), (float) $facts['scenario.lowest_cash:business:current']->value,
            'The trough has to be the minimum of the curve SS-01 reconciles it against.');
        self::assertSame('lowest_month_end_closing_balance', $facts['scenario.lowest_cash:business:current']->basis);
    }

    public function test_a_funding_source_is_published_with_the_provenance_a_reconciliation_needs(): void
    {
        $facts = $this->keyed($this->capture($this->cashModel(funded: true)));

        self::assertSame('25000', $facts['funding.amount:term-loan:current']->value);
        self::assertTrue($facts['funding.amount:term-loan:current']->confirmed, 'Evidence sighted is what confirms the figure.');
        self::assertSame(['Westpac', 'Term loan', 'committed', '2026-02-01', '2026-02-15', 'unrestricted'], [
            $facts['funding.identity:term-loan:current']->value, $facts['funding.type:term-loan:current']->value,
            $facts['funding.commitment:term-loan:current']->value, $facts['funding.availability:term-loan:current']->value,
            $facts['funding.draw_date:term-loan:current']->value, $facts['funding.restriction:term-loan:current']->value,
        ]);
        // The draw reaches the cash ledger as a financing inflow and carries
        // the source it came from, so every inflow maps to a declared source.
        self::assertSame('financing_inflows', $facts['ledger.category:term-loan.draw:month:2']->value);
        self::assertSame('Westpac', $facts['ledger.funding_identity:term-loan.draw:month:2']->value);
    }

    public function test_no_funding_total_is_stated_until_somebody_says_the_inventory_is_finished(): void
    {
        // The same sources, with nobody having said whether that is all of
        // them. A sum of a list nobody has finished is not a total.
        $partial = $this->keyed($this->capture($this->cashModel(funded: true, inventoryComplete: null)));
        self::assertArrayNotHasKey('model.funding_total:business:current', $partial);
        self::assertArrayNotHasKey('model.available_funds:business:current', $partial);
        self::assertArrayHasKey('funding.amount:term-loan:current', $partial, 'The source itself is still evidence.');
        self::assertArrayNotHasKey('funding.inventory_complete:business:current', $partial,
            'An unanswered question is absent, never an answer of no.');

        $complete = $this->keyed($this->capture($this->cashModel(funded: true)));
        self::assertSame('25000', $complete['model.funding_total:business:current']->value);
        // 5000 already in the bank, 25000 arranged.
        self::assertSame('30000', $complete['model.available_funds:business:current']->value);
        self::assertTrue($complete['funding.inventory_complete:business:current']->value);
    }

    public function test_one_source_without_an_amount_withholds_the_total_the_others_would_imply(): void
    {
        $facts = $this->keyed($this->capture($this->cashModel(funded: true, unpriced: true)));

        self::assertArrayNotHasKey('model.funding_total:business:current', $facts,
            'A total that quietly leaves out the source nobody has priced would understate the funding.');
        self::assertArrayNotHasKey('model.available_funds:business:current', $facts);
    }

    /**
     * A cash-shaped reconstruction: an opening balance, one receivable
     * settling in February and a capital asset paid the same day.
     *
     * @param  list<TradeItem>  $tradeItems
     */
    private function cashModel(int $months = 3, ?array $tradeItems = null, bool $funded = false,
        ?bool $inventoryComplete = true, bool $unpriced = false): ForecastModelInputs
    {
        $sources = ! $funded ? [] : [new FundingSource('term-loan', 'Signed facility letter', 'Westpac', 'Term loan',
            '25000', 'committed', '2026-02-01', '2026-02-15', 'unrestricted', true)];
        if ($funded && $unpriced) {
            $sources[] = new FundingSource('grant', 'Grant letter', 'Callaghan', 'Grant', null, 'indicative', null, null, null, null);
        }

        return new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', $months),
            cashOpening: new CashOpening('5000', 'Bank statement to 31 December 2025', true),
            tradeItems: $tradeItems ?? [new TradeItem('invoice-1', 'Sales ledger export', 'receivable', '2026-01-05', '900', [
                new CashMovement('invoice-1.payment', 'Bank statement', '2026-02-10', '900', 'in', 'operating_receipt', 0, true),
            ])],
            assets: $tradeItems === null ? [new AssetInput('laptop', 'Supplier invoice 8831', '2400', 'capital',
                'Capitalisation policy v2', true, '2026-02-10', '2026-02-01', 36, '200', 'straight_line_full_start_month', '0')] : [],
            fundingSources: $sources,
            fundingInventoryComplete: $funded ? $inventoryComplete : null,
        );
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

        return $this->capture($inputs);
    }

    /**
     * Captures a reconstruction the way the gate does: through the stored
     * payload and back out through the engine's own codec, so a model this
     * test builds has to survive the round trip the producer relies on.
     *
     * @return list<Fact>
     */
    private function capture(ForecastModelInputs $inputs): array
    {
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
