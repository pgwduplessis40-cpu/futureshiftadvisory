<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\ActualProfit;
use App\Services\Entrepreneurs\Forecast\ActualsRebase;
use App\Services\Entrepreneurs\Forecast\ActualsWindow;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Entrepreneurs\Forecast\ActualsFixture as F;

final class ActualsRebaseTest extends TestCase
{
    public function test_reviewed_actuals_freeze_history_and_carry_cash_and_unpaid_obligations_once(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/Forecast/assets-tax-actuals.json'), true, flags: JSON_THROW_ON_ERROR);
        $records = F::twoMonths();
        $result = (new ActualsRebase)->calculate(new ForecastCalendar('2027-01', 4), '2027-03-01', 'synthetic-business', $records);
        self::assertSame('pass', $result->status);
        self::assertSame('2027-02', $result->cutoff);
        self::assertSame($fixture['expected_actuals']['forecast_periods'], $result->forecastPeriods);
        self::assertSame($fixture['expected_actuals']['opening_forecast_cash'], $result->forecastOpening->bankCash);
        self::assertSame(['outstanding', 'later-payment'], array_column($result->outstandingMovements(), 'id'));
        self::assertSame([$fixture['expected_actuals']['outstanding_receipt'], $fixture['expected_actuals']['beyond_horizon_payment']], array_column($result->outstandingMovements(), 'amount'));
        self::assertSame('2027-06-01', $result->outstandingMovements()[1]->date);
        self::assertSame($records, $result->actualMonths);
        self::assertSame($result->historyHash, (new ActualsRebase)->calculate(new ForecastCalendar('2027-01', 4), '2027-03-01', 'synthetic-business', array_reverse($records))->historyHash);
        $result->assertForecastPeriods(['2027-03']);
        self::assertSame('2027-02', $result->jsonSerialize()['cutoff']);
        self::assertSame('trading', $records[0]->jsonSerialize()['state']);
    }

    public function test_every_completed_month_is_required_and_missing_history_blocks_opening(): void
    {
        $result = (new ActualsRebase)->calculate(new ForecastCalendar('2027-01', 4), '2027-03-01', 'synthetic-business', [F::month('2027-02')]);
        self::assertSame('cannot_verify', $result->status);
        self::assertContains('2027-01.actual_record', $result->missing);
        self::assertNull($result->forecastOpening);
        self::assertNull($result->openingObligations);
        self::assertNull($result->outstandingMovements());
        $this->expectException(\LogicException::class);
        $result->assertForecastPeriods(['2027-03']);
    }

    public function test_no_elapsed_months_do_not_invent_a_bank_opening_and_month_end_counts_as_completed(): void
    {
        $engine = new ActualsRebase;
        $calendar = new ForecastCalendar('2027-01', 1);
        $future = $engine->calculate($calendar, '2027-01-30', 'synthetic-business', []);
        self::assertSame('not_applicable', $future->status);
        self::assertNull($future->cutoff);
        self::assertNull($future->forecastOpening);
        $future->assertForecastPeriods(['2027-01']);
        $elapsed = $engine->calculate($calendar, '2027-01-31', 'synthetic-business', [F::month()]);
        self::assertSame('pass', $elapsed->status);
        self::assertSame([], $elapsed->forecastPeriods);
        self::assertSame([], $elapsed->outstandingMovements());
    }

    public function test_pre_trading_needs_evidence_and_can_contain_startup_costs_and_funding(): void
    {
        $month = F::month(changes: ['state' => 'pre_trading', 'preTradingEvidence' => 'reviewed-start-date',
            'profit' => new ActualProfit('0', '20', '0', '0', '0', '-20', '0'), 'opening' => F::balances('0'), 'closing' => F::balances('80'),
            'movements' => [F::movement('funding', '2027-01-01', '100', 'in', 'funding'), F::movement('cost', '2027-01-02', '20', 'out', 'operating_payment')],
            'movementAccounts' => ['funding' => 'document:cash.funding', 'cost' => 'document:cash.operating_payment']]);
        self::assertSame('pass', $this->calculate($month)->status);
        $unknown = $this->calculate(F::month(changes: ['state' => 'pre_trading']));
        self::assertSame('cannot_verify', $unknown->status);
        self::assertContains('2027-01.pre_trading_evidence', $unknown->missing);
        $failure = $this->calculate(F::month(changes: ['state' => 'pre_trading', 'preTradingEvidence' => 'review', 'profit' => new ActualProfit('1', '0', '0', '0', '0', '1', '0')]));
        self::assertContains('2027-01.pre_trading_has_revenue', $failure->errors);
    }

    #[DataProvider('unusableSources')]
    public function test_fake_degraded_unreviewed_or_incomplete_sources_cannot_be_rebased(array $changes): void
    {
        $result = $this->calculate(F::month(changes: ['source' => F::source(changes: $changes)]));
        self::assertSame('cannot_verify', $result->status);
        self::assertNull($result->forecastOpening);
    }

    public static function unusableSources(): array
    {
        return [[['provider' => 'xero', 'badge' => 'fake']], [['provider' => 'xero', 'badge' => 'live_no_data']], [['provider' => 'xero', 'badge' => 'live', 'degraded' => true]],
            [['provider' => 'manual', 'badge' => 'live']], [['reconciled' => false]], [['reviewedBy' => null]], [['reviewReference' => '']], [['reviewedOn' => null]],
            [['importVersion' => null]], [['accountMappings' => null]], [['provider' => null]], [['degraded' => null]]];
    }

    public function test_live_non_degraded_xero_is_eligible_only_with_complete_monthly_review(): void
    {
        self::assertSame('pass', $this->calculate(F::month(changes: ['source' => F::source(changes: ['provider' => 'xero', 'badge' => 'live'])]))->status);
    }

    #[DataProvider('mismatchedSources')]
    public function test_known_source_scope_mismatches_fail_even_if_another_input_is_missing(array $changes, string $error): void
    {
        $result = $this->calculate(F::month(changes: ['source' => F::source(changes: [...$changes, 'importVersion' => null])]));
        self::assertSame('fail', $result->status);
        self::assertContains('2027-01.source.'.$error, $result->errors);
        self::assertContains('2027-01.source.import_version', $result->missing);
    }

    public static function mismatchedSources(): array
    {
        return [[['entityId' => 'other-business'], 'entity_scope_mismatch'], [['currency' => 'USD'], 'currency_mismatch'],
            [['periodStart' => '2027-01-02'], 'period_mismatch'], [['periodEnd' => '2027-02-01'], 'period_mismatch'],
            [['reviewedOn' => '2027-01-30'], 'review_precedes_completed_period']];
    }

    public function test_unknown_financial_lines_are_not_zero_and_empty_account_mapping_requires_a_known_zero(): void
    {
        $result = $this->calculate(F::month(changes: ['state' => null, 'profit' => new ActualProfit(null, null, null, null, null, null, null),
            'opening' => F::balances(null, null, null, null), 'closing' => F::balances(null, null, null, null), 'movements' => null, 'movementAccounts' => null, 'closingObligations' => null]));
        self::assertSame('cannot_verify', $result->status);
        self::assertContains('2027-01.profit.revenue', $result->missing);
        self::assertNull($result->forecastOpening);
        $mappings = F::source()->accountMappings;
        $mappings['profit.revenue'] = [];
        self::assertSame('pass', $this->calculate(F::month(changes: ['source' => F::source(changes: ['accountMappings' => $mappings])]))->status);
        $mappings['balance.bank_cash'] = [];
        self::assertSame('cannot_verify', $this->calculate(F::month(changes: ['source' => F::source(changes: ['accountMappings' => $mappings])]))->status);
    }

    public function test_profit_bank_restricted_and_trade_reconciliations_detect_known_errors(): void
    {
        $cases = [
            [F::month(changes: ['profit' => new ActualProfit('100', '20', '10', '5', '2', '68', '0')]), 'profit.bridge_mismatch'],
            [F::month(changes: ['closing' => F::balances('101')]), 'bank.closing_reconciliation_mismatch'],
            [F::month(changes: ['closing' => F::balances(restricted: '101')]), 'restricted_cash_exceeds_bank'],
            [F::month(changes: ['closing' => F::balances(ar: '1')]), 'obligations.receivables_mismatch'],
        ];
        foreach ($cases as [$month, $error]) {
            $result = $this->calculate($month);
            self::assertSame('fail', $result->status);
            self::assertContains('2027-01.'.$error, $result->errors);
        }
        $result = (new ActualsRebase)->calculate(new ForecastCalendar('2027-01', 3), '2027-03-01', 'synthetic-business', [F::month(), F::month('2027-02', ['opening' => F::balances('101'), 'closing' => F::balances('101')])]);
        self::assertContains('2027-02.opening_bank_cash_does_not_match_prior_close', $result->errors);
    }

    public function test_cash_mappings_must_bind_valid_current_period_bank_rows(): void
    {
        $movement = F::movement('receipt', '2027-01-10', '10', 'in', 'operating_receipt');
        $base = ['movements' => [$movement], 'closing' => F::balances('110')];
        $unknown = $this->calculate(F::month(changes: [...$base, 'movementAccounts' => []]));
        self::assertContains('2027-01.receipt.account_mapping', $unknown->missing);
        $bad = $this->calculate(F::month(changes: [...$base, 'movementAccounts' => ['receipt' => 'wrong', 'extra' => 'unused']]));
        self::assertContains('2027-01.receipt.account_mapping_mismatch', $bad->errors);
        self::assertContains('2027-01.movement_mapping_has_unbound_rows', $bad->errors);
        foreach ([F::movement('outside', '2027-02-01', '10', 'in', 'operating_receipt'), F::movement('internal', '2027-01-10', '10', 'out', 'restricted_transfer')] as $invalid) {
            self::assertContains('2027-01.'.$invalid->id.'.not_an_in_period_bank_movement', $this->calculate(F::month(changes: ['movements' => [$invalid]]))->errors);
        }
    }

    public function test_unknown_or_unallocated_obligations_cannot_silently_disappear_from_forecast(): void
    {
        $unknown = $this->calculate(F::month(changes: ['closingObligations' => [new TradeItem('bill', 'source', 'payable', null, null, null)]]));
        self::assertSame('cannot_verify', $unknown->status);
        $unscheduled = $this->calculate(F::month(changes: ['closing' => F::balances(ap: '40'), 'closingObligations' => [new TradeItem('bill', 'source', 'payable', '2027-01-10', '40', [])]]));
        self::assertContains('2027-01.obligations.bill.remaining_settlement_schedule', $unscheduled->missing);
        self::assertNull($unscheduled->openingObligations);
        $unbound = F::movement('unbound', '2027-01-20', '10', 'out', 'operating_payment');
        $bad = $this->calculate(F::month(changes: ['closingObligations' => [new TradeItem('bill', 'source', 'payable', '2027-01-10', '5', [$unbound])]]));
        self::assertSame('fail', $bad->status);
        self::assertContains('2027-01.obligations.unbound.bank_settlement_mismatch', $bad->errors);
        self::assertContains('2027-01.obligations.bill.settlements_exceed_amount', $bad->errors);
    }

    public function test_source_corrections_change_history_hash_and_forecast_cannot_edit_actuals(): void
    {
        $first = $this->calculate(F::month());
        $corrected = $this->calculate(F::month(changes: ['source' => F::source(changes: ['importVersion' => 'v2'])]));
        self::assertNotSame($first->historyHash, $corrected->historyHash);
        $this->expectException(\InvalidArgumentException::class);
        $first->assertForecastPeriods(['2027-01']);
    }

    public function test_actual_objects_are_immutable(): void
    {
        $window = $this->calculate(F::month());
        $this->expectException(\Error::class);
        $window->actualMonths[0]->closing->bankCash = '999';
    }

    #[DataProvider('invalidWindows')]
    public function test_duplicate_future_outside_or_unscoped_actuals_are_rejected(array $periods, string $entity): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ActualsRebase)->calculate(new ForecastCalendar('2027-01', 3), '2027-02-01', $entity, array_map(fn (string $p): ActualMonth => F::month($p), $periods));
    }

    public static function invalidWindows(): array
    {
        return [[['2027-01', '2027-01'], 'synthetic-business'], [['2027-02'], 'synthetic-business'], [['2026-12'], 'synthetic-business'], [[], '']];
    }

    private function calculate(ActualMonth $month): ActualsWindow
    {
        return (new ActualsRebase)->calculate(new ForecastCalendar('2027-01', 3), '2027-02-01', 'synthetic-business', [$month]);
    }
}
