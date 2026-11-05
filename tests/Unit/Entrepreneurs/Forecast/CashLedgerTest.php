<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\CashLedger;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CashLedgerTest extends TestCase
{
    public function test_later_funding_does_not_erase_an_earlier_deficit_or_count_opening_cash_twice(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/Forecast/dated-cash.json'), true, flags: JSON_THROW_ON_ERROR);
        $movements = array_map(fn (array $m): CashMovement => new CashMovement(...$m), $fixture['movements']);
        $result = $this->calculate($movements, new CashOpening($fixture['opening_cash'], 'opening-reconciliation', true));
        self::assertSame($fixture['expected']['lowest_cash'], $result['lowest_cash']);
        self::assertSame($fixture['expected']['lowest_cash_date'], $result['lowest_cash_date']);
        self::assertSame($fixture['expected']['closing_cash'], $result['months'][0]['closing_cash']);
        self::assertSame(['100.00', '-50.00', '150.00'], array_column($result['checkpoints'], 'balance'));
        self::assertSame('200.00', $result['months'][0]['receipts']);
        self::assertSame('150.00', $result['months'][0]['payments']);
        self::assertSame(['operating_payment' => '-150.00', 'funding' => '200.00'], $result['months'][0]['by_category']);
        self::assertFalse($result['bank_reconciled']);
        self::assertSame('pass', $result['status']);
        self::assertSame([], $fixture['enabled_rule_ids']);
        self::assertEquals($result, $this->calculate(array_reverse($movements), new CashOpening('100.00', 'opening-reconciliation', true)));
    }

    public function test_same_day_order_and_opening_checkpoint_are_retained(): void
    {
        $out = $this->movement('out', '2027-01-01', '150', 'out', sequence: 1);
        $in = $this->movement('in', '2027-01-01', '200', 'in', sequence: 2);
        $result = $this->calculate([$in, $out]);
        self::assertSame('-50.00', $result['lowest_cash']);
        self::assertSame('150.00', $result['months'][0]['closing_cash']);
        $negativeOpening = $this->calculate([$in], new CashOpening('-100', 'overdraft', true));
        self::assertSame('-100.00', $negativeOpening['lowest_cash']);
        self::assertSame('2027-01-01', $negativeOpening['lowest_cash_date']);
        self::assertSame('100.00', $negativeOpening['months'][0]['closing_cash']);
    }

    public function test_deferred_cash_remains_visible_and_does_not_enter_the_current_month(): void
    {
        $result = $this->calculate([$this->movement('later', '2027-02-01', '50', 'in')]);
        self::assertSame('100.00', $result['lowest_cash']);
        self::assertSame('100.00', $result['months'][0]['closing_cash']);
        self::assertSame('0.00', $result['months'][0]['receipts']);
        self::assertCount(1, $result['deferred']);
        self::assertSame('later', $result['deferred'][0]->id);
    }

    public function test_unknown_dates_and_amounts_are_not_silently_zero(): void
    {
        $undated = $this->calculate([$this->movement('unknown', null, '50', 'in')]);
        self::assertSame('cannot_verify', $undated['status']);
        self::assertNull($undated['months'][0]['opening_cash']);
        self::assertNull($undated['months'][0]['closing_cash']);
        self::assertNull($undated['months'][0]['receipts']);
        self::assertNull($undated['lowest_cash']);
        self::assertCount(1, $undated['undated']);
        $unknown = $this->calculate([$this->movement('unknown', '2027-01-15', null, 'out')]);
        self::assertSame('100.00', $unknown['checkpoints'][0]['balance']);
        self::assertNull($unknown['lowest_cash']);
        self::assertNull($unknown['months'][0]['closing_cash']);
        self::assertNull($unknown['months'][0]['payments']);
        self::assertContains('unknown.amount', $unknown['missing']);
        $opening = $this->calculate([], new CashOpening(null, null, null));
        self::assertSame(['opening.amount', 'opening.source', 'opening.verification'], $opening['missing']);
        self::assertNull($opening['months'][0]['closing_cash']);
    }

    public function test_unverified_provenance_blocks_verification_without_hiding_known_arithmetic(): void
    {
        $result = $this->calculate([new CashMovement('loan', 'unconfirmed-offer', '2027-01-02', '200', 'in', 'funding', 0, false)], new CashOpening('100', '', false));
        self::assertSame('cannot_verify', $result['status']);
        self::assertSame('300.00', $result['months'][0]['closing_cash']);
        self::assertContains('loan.verification', $result['missing']);
        self::assertContains('opening.source', $result['missing']);
    }

    public function test_unknown_category_totals_stay_unknown_after_a_later_known_movement(): void
    {
        $result = (new CashLedger)->calculate(new ForecastCalendar('2027-01', 2), new CashOpening('100', 'bank', true), [
            $this->movement('unknown', '2027-02-01', null, 'out'), $this->movement('known', '2027-02-02', '10', 'out'),
        ]);
        self::assertSame('100.00', $result['months'][0]['closing_cash']);
        self::assertNull($result['months'][1]['closing_cash']);
        self::assertNull($result['months'][1]['by_category']['operating_payment']);
        $undated = $this->calculate([$this->movement('unknown', null, '10', 'in'), $this->movement('known', '2027-01-02', '10', 'in')]);
        self::assertNull($undated['months'][0]['by_category']['operating_receipt']);
    }

    public function test_every_cash_bridge_category_retains_its_source_and_cent_amount(): void
    {
        $movements = [];
        foreach (['owner_pay', 'capex', 'debt_principal', 'distribution', 'interest', 'fees', 'tax', 'gst'] as $index => $category) {
            $movements[] = new CashMovement($category, 'source-'.$category, '2027-01-03', '1.005', 'out', $category, $index);
        }
        $movements[] = new CashMovement('tax-refund', 'confirmed-refund', '2027-01-04', '2', 'in', 'tax', 0);
        $result = $this->calculate($movements);
        self::assertSame('93.92', $result['months'][0]['closing_cash']);
        self::assertSame('8.08', $result['months'][0]['payments']);
        self::assertSame('0.99', $result['months'][0]['by_category']['tax']);
        self::assertCount(9, $result['movements']);
        self::assertNotSame($result['input_hash'], $this->calculate([])['input_hash']);
    }

    #[DataProvider('invalidMovements')]
    public function test_duplicate_historical_or_ambiguous_movements_are_rejected(array $movements): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculate($movements);
    }

    public static function invalidMovements(): array
    {
        $one = new CashMovement('one', 'source', '2027-01-01', '1', 'in', 'operating_receipt', 0);

        return [[[$one, $one]], [[$one, new CashMovement('two', 'source', '2027-01-01', '2', 'in', 'operating_receipt', 0)]],
            [[new CashMovement('past', 'source', '2026-12-31', '1', 'in', 'operating_receipt', 0)]]];
    }

    private function movement(string $id, ?string $date, ?string $amount, string $direction, int $sequence = 0): CashMovement
    {
        return new CashMovement($id, 'synthetic-source', $date, $amount, $direction, $direction === 'in' ? 'operating_receipt' : 'operating_payment', $sequence);
    }

    private function calculate(array $movements, ?CashOpening $opening = null): array
    {
        return (new CashLedger)->calculate(new ForecastCalendar('2027-01', 1), $opening ?? new CashOpening('100', 'opening-source', true), $movements);
    }
}
