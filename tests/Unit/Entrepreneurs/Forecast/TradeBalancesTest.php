<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\CashLedger;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\TradeBalances;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use App\Services\Entrepreneurs\Forecast\TradeTerms;
use PHPUnit\Framework\TestCase;

final class TradeBalancesTest extends TestCase
{
    public function test_booking_deposit_and_pre_delivery_balance_become_advances_before_the_invoice(): void
    {
        $sale = (new TradeTerms)->sale('sale', 'contract', '1000', '2027-01-10', '2027-02-05', '2027-02-05', '30', 'before_delivery', 5, 0);
        self::assertSame(['2027-01-10', '2027-01-31'], array_map(fn ($m) => $m->date, $sale->settlements));
        self::assertSame(['300.00', '700.00'], array_map(fn ($m) => $m->amount, $sale->settlements));
        $result = (new TradeBalances)->calculate(new ForecastCalendar('2027-01', 2), [$sale]);
        self::assertSame('1000.00', $result['closing'][0]['customer_advances']);
        self::assertSame('0.00', $result['closing'][0]['receivables']);
        self::assertSame('0.00', $result['closing'][1]['customer_advances']);
        self::assertSame('0.00', $result['closing'][1]['receivables']);
        self::assertSame('pass', $result['status']);
    }

    public function test_exact_debtor_days_retain_receivables_beyond_horizon_and_cross_leap_day(): void
    {
        $sale = (new TradeTerms)->sale('sale', 'contract', '100', null, '2028-02-28', null, '0', 'invoice_days', 2, 0);
        self::assertCount(1, $sale->settlements);
        self::assertSame('2028-03-01', $sale->settlements[0]->date);
        $calendar = new ForecastCalendar('2028-02', 1);
        $trade = (new TradeBalances)->calculate($calendar, [$sale]);
        $cash = (new CashLedger)->calculate($calendar, new CashOpening('0', 'opening', true), $sale->settlements);
        self::assertSame('100.00', $trade['closing'][0]['receivables']);
        self::assertSame('0.00', $cash['months'][0]['closing_cash']);
        self::assertCount(1, $cash['deferred']);
    }

    public function test_opening_ar_ap_and_supplier_prepayments_are_separate_from_opening_cash(): void
    {
        $ar = new TradeItem('opening-ar', 'invoice-before-start', 'receivable', '2026-12-20', '40', [new CashMovement('ar-paid', 'collection', '2027-01-10', '40', 'in', 'operating_receipt', 0)]);
        $ap = (new TradeTerms)->purchase('opening-ap', 'bill-before-start', '20', '2026-12-20', 30, 1);
        $prepaid = new TradeItem('prepaid', 'supplier-agreement', 'payable', '2027-02-01', '60', [new CashMovement('prepaid-paid', 'supplier-payment', '2027-01-15', '60', 'out', 'operating_payment', 0)]);
        $result = (new TradeBalances)->calculate(new ForecastCalendar('2027-01', 2), [$ar, $ap, $prepaid]);
        self::assertSame('40.00', $result['opening']['receivables']);
        self::assertSame('20.00', $result['opening']['payables']);
        self::assertSame('0.00', $result['closing'][0]['receivables']);
        self::assertSame('0.00', $result['closing'][0]['payables']);
        self::assertSame('60.00', $result['closing'][0]['supplier_prepayments']);
        self::assertSame('0.00', $result['closing'][1]['supplier_prepayments']);
        $cash = (new CashLedger)->calculate(new ForecastCalendar('2027-01', 1), new CashOpening('100', 'bank', true), [...$ar->settlements, ...$ap->settlements, ...$prepaid->settlements]);
        self::assertSame('60.00', $cash['months'][0]['closing_cash']);
    }

    public function test_partial_settlements_and_payables_due_beyond_horizon_remain_visible(): void
    {
        $ap = (new TradeTerms)->purchase('bill', 'supplier', '600', '2027-01-31', 30, 0);
        self::assertSame('2027-03-02', $ap->settlements[0]->date);
        $ar = new TradeItem('sale', 'invoice', 'receivable', '2027-01-01', '100', [new CashMovement('part', 'receipt', '2027-01-02', '30', 'in', 'operating_receipt', 0)]);
        $result = (new TradeBalances)->calculate(new ForecastCalendar('2027-01', 2), [$ap, $ar]);
        self::assertSame('600.00', $result['closing'][1]['payables']);
        self::assertSame('70.00', $result['closing'][1]['receivables']);
    }

    public function test_missing_settlements_and_dates_are_unknown_but_explicit_no_collections_leave_ar(): void
    {
        $engine = new TradeBalances;
        $calendar = new ForecastCalendar('2027-01', 1);
        $unknown = new TradeItem('unknown', 'source', 'receivable', null, null, null);
        $result = $engine->calculate($calendar, [$unknown]);
        self::assertSame('cannot_verify', $result['status']);
        self::assertNull($result['closing'][0]['receivables']);
        self::assertCount(3, $result['missing']);
        $noCollections = new TradeItem('known', 'source', 'receivable', '2027-01-01', '100', []);
        self::assertSame('100.00', $engine->calculate($calendar, [$noCollections])['closing'][0]['receivables']);
        $missingTerms = (new TradeTerms)->sale('sale', 'contract', '100', null, '2027-01-01', null, null, null, null, 0);
        self::assertSame('cannot_verify', $engine->calculate($calendar, [$missingTerms])['status']);
    }

    public function test_over_settlement_remains_a_known_failure_with_other_unknowns(): void
    {
        $item = new TradeItem('over', 'invoice', 'receivable', null, '10', [new CashMovement('paid', 'receipt', null, '11', 'in', 'operating_receipt', 0)]);
        $result = (new TradeBalances)->calculate(new ForecastCalendar('2027-01', 1), [$item]);
        self::assertSame('fail', $result['status']);
        self::assertSame(['over.settlements_exceed_amount'], $result['errors']);
        self::assertNotEmpty($result['missing']);
        self::assertNull($result['closing'][0]['receivables']);
    }

    public function test_deposit_rounding_reconciles_to_the_sale_and_full_deposits_need_no_balance_date(): void
    {
        $terms = new TradeTerms;
        $sale = $terms->sale('sale', 'contract', '0.05', '2027-01-01', '2027-01-02', null, '50', 'invoice_days', 0, 0);
        self::assertSame(['0.03', '0.02'], array_map(fn ($m) => $m->amount, $sale->settlements));
        $full = $terms->sale('full', 'contract', '10', '2027-01-01', '2027-01-02', null, '100', null, null, 0);
        self::assertCount(1, $full->settlements);
        self::assertSame('2027-01-01', $full->settlements[0]->date);
        $zero = $terms->sale('zero', 'contract', '0', null, '2027-01-01', null, '0', null, null, 0);
        self::assertSame([], $zero->settlements);
    }

    public function test_duplicate_trade_identity_is_rejected(): void
    {
        $item = new TradeItem('duplicate', 'source', 'payable', '2027-01-01', '10', []);
        $this->expectException(\InvalidArgumentException::class);
        (new TradeBalances)->calculate(new ForecastCalendar('2027-01', 1), [$item, $item]);
    }

    public function test_duplicate_settlement_allocation_is_rejected(): void
    {
        $paid = new CashMovement('paid', 'source', '2027-01-01', '10', 'in', 'operating_receipt', 0);
        $this->expectException(\InvalidArgumentException::class);
        (new TradeBalances)->calculate(new ForecastCalendar('2027-01', 1), [new TradeItem('a', 'a', 'receivable', '2027-01-01', '10', [$paid]), new TradeItem('b', 'b', 'receivable', '2027-01-01', '10', [$paid])]);
    }
}
