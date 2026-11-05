<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastDates;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use App\Services\Entrepreneurs\Forecast\TradeTerms;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CashContractTest extends TestCase
{
    public function test_calendar_days_and_anchored_recurrence_do_not_drift_at_month_end(): void
    {
        self::assertNull(ForecastDates::read(null));
        self::assertNull(ForecastDates::shift('2027-01-01', null));
        self::assertNull(ForecastDates::shift(null, 1));
        self::assertSame('2028-02-29', ForecastDates::shift('2028-03-01', -1));
        self::assertSame(['2028-01-31', '2028-02-29', '2028-03-31'], ForecastDates::recur('2028-01-31', '2028-03-31', 'monthly'));
        self::assertSame(['2027-01-31', '2027-04-30', '2027-07-31'], ForecastDates::recur('2027-01-31', '2027-07-31', 'quarterly'));
        self::assertSame(['2028-02-29', '2029-02-28'], ForecastDates::recur('2028-02-29', '2029-02-28', 'annual'));
    }

    public function test_input_serialisation_retains_unknowns_and_sources(): void
    {
        $movement = new CashMovement('id', 'source', null, null, 'out', 'tax', 7);
        $item = new TradeItem('trade', 'invoice-source', 'payable', null, null, [$movement]);
        self::assertSame('invoice-source', $item->jsonSerialize()['source']);
        self::assertSame([$movement], $item->jsonSerialize()['settlements']);
        self::assertNull($movement->jsonSerialize()['date']);
        self::assertSame(['amount' => '-10', 'source' => null, 'verified' => false], (new CashOpening('-10', null, false))->jsonSerialize());
        self::assertSame('base', (new OwnerPayStep('2027-01-01', null, 'base'))->jsonSerialize()['trigger']);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_cash_contracts_are_rejected(string $case): void
    {
        $this->expectException(\InvalidArgumentException::class);
        match ($case) {
            'date' => ForecastDates::read('2027-02-29'),
            'date-format' => ForecastDates::read('2027-1-01'),
            'days' => ForecastDates::shift('2027-01-01', 36501),
            'recurrence-order' => ForecastDates::recur('2027-02-01', '2027-01-01', 'weekly'),
            'recurrence-frequency' => ForecastDates::recur('2027-01-01', '2027-02-01', 'daily'),
            'recurrence-limit' => ForecastDates::recur('2000-01-01', '2100-01-01', 'weekly'),
            'cash-id' => new CashMovement('', 'source', null, null, 'in', 'funding', 0),
            'cash-source' => new CashMovement('id', '', null, null, 'in', 'funding', 0),
            'cash-order' => new CashMovement('id', 'source', null, null, 'in', 'funding', -1),
            'cash-category' => new CashMovement('id', 'source', null, null, 'in', 'unknown', 0),
            'cash-direction' => new CashMovement('id', 'source', null, null, 'in', 'owner_pay', 0),
            'cash-negative' => new CashMovement('id', 'source', null, '-1', 'out', 'owner_pay', 0),
            'opening' => new CashOpening('--1', 'source', true),
            'trade-id' => new TradeItem('', 'source', 'payable', null, null, null),
            'trade-kind' => new TradeItem('id', 'source', 'unknown', null, null, null),
            'trade-direction' => new TradeItem('id', 'source', 'payable', null, null, [new CashMovement('paid', 'source', null, null, 'in', 'operating_receipt', 0)]),
            'owner-trigger' => new OwnerPayStep('2027-01-01', '10', 'unknown'),
            'owner-base-threshold' => new OwnerPayStep('2027-01-01', '10', 'base', '100'),
            'booking-order' => (new TradeTerms)->sale('id', 'source', '10', '2027-02-01', '2027-01-01', '2027-01-01', '0', 'invoice_days', 0, 0),
            'balance-basis' => (new TradeTerms)->sale('id', 'source', '10', null, null, null, '0', 'unknown', 0, 0),
            'deposit' => (new TradeTerms)->sale('id', 'source', '10', null, null, null, '101', null, null, 0),
            'negative-days' => (new TradeTerms)->purchase('id', 'source', '10', null, -1, 0),
        };
    }

    public static function invalidInputs(): array
    {
        return array_map(fn ($case) => [$case], ['date', 'date-format', 'days', 'recurrence-order', 'recurrence-frequency', 'recurrence-limit', 'cash-id', 'cash-source', 'cash-order', 'cash-category', 'cash-direction', 'cash-negative', 'opening', 'trade-id', 'trade-kind', 'trade-direction', 'owner-trigger', 'owner-base-threshold', 'booking-order', 'balance-basis', 'deposit', 'negative-days']);
    }
}
