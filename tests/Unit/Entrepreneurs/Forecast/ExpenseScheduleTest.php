<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\CashLedger;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ExpenseSchedule;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\TradeBalances;
use PHPUnit\Framework\TestCase;

final class ExpenseScheduleTest extends TestCase
{
    public function test_annual_bill_is_spread_for_expense_but_paid_on_its_actual_creditor_date(): void
    {
        $calendar = new ForecastCalendar('2027-01', 12);
        $result = (new ExpenseSchedule)->fixed($calendar, 'annual', 'annual-contract', '600', '2', 'annual', '2027-01-31', '2027-12-31', array_fill_keys($calendar->periods, '1'), 20, 0);
        self::assertSame('pass', $result['status']);
        self::assertSame(['fixed', '600', '2', 'annual'], [$result['basis'], $result['rate'], $result['quantity'], $result['frequency']]);
        self::assertSame(array_fill_keys($calendar->periods, '100.00'), $result['recognition']);
        self::assertCount(1, $result['obligations']);
        self::assertSame('1200.00', $result['obligations'][0]->amount);
        self::assertSame('2027-02-20', $result['obligations'][0]->settlements[0]->date);
        $cash = (new CashLedger)->calculate($calendar, new CashOpening('2000', 'bank', true), $result['obligations'][0]->settlements);
        self::assertSame('0.00', $cash['months'][0]['payments']);
        self::assertSame('1200.00', $cash['months'][1]['payments']);
        $trade = (new TradeBalances)->calculate($calendar, $result['obligations']);
        self::assertSame('1200.00', $trade['closing'][0]['payables']);
        self::assertSame('0.00', $trade['closing'][1]['payables']);
    }

    public function test_fixed_frequency_quantity_and_cent_residual_are_effective(): void
    {
        $calendar = new ForecastCalendar('2027-01', 3);
        $engine = new ExpenseSchedule;
        $monthly = $engine->fixed($calendar, 'monthly', 'contract', '10', '2', 'monthly', '2027-01-31', '2027-03-31', ['2027-01' => '1', '2027-02' => '1', '2027-03' => '1'], 0, 0);
        self::assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], array_map(fn ($o) => $o->raisedOn, $monthly['obligations']));
        self::assertSame(['20.00', '20.00', '20.00'], array_values($monthly['recognition']));
        $oneOff = $engine->fixed($calendar, 'one', 'contract', '100', '1', 'once', '2027-01-01', '2027-03-31', ['2027-03' => '1', '2027-01' => '1', '2027-02' => '1'], 0, 0);
        self::assertSame(['33.33', '33.33', '33.34'], array_values($oneOff['recognition']));
        $smallCalendar = new ForecastCalendar('2027-01', 4);
        $cents = $engine->fixed($smallCalendar, 'cents', 'contract', '0.02', '1', 'once', '2027-01-01', '2027-01-01', array_fill_keys($smallCalendar->periods, '1'), 0, 0);
        self::assertSame(['0.00', '0.00', '0.00', '0.02'], array_values($cents['recognition']));
        $zero = $engine->fixed($calendar, 'zero', 'contract', '0', '1', 'once', '2027-01-01', '2027-01-01', ['2027-01' => '1', '2027-02' => '0'], 0, 0);
        self::assertSame(['0.00', '0.00', '0.00'], array_values($zero['recognition']));
    }

    public function test_unknown_fixed_inputs_are_not_replaced_with_zero_or_a_same_day_payment(): void
    {
        $calendar = new ForecastCalendar('2027-01', 1);
        $engine = new ExpenseSchedule;
        foreach ([[null, ['2027-01' => '1']], ['10', ['2027-01' => null]], ['10', []], ['10', ['2027-01' => '0']]] as [$rate, $weights]) {
            $result = $engine->fixed($calendar, 'cost', 'contract', $rate, '1', 'once', '2027-01-01', '2027-01-01', $weights, null, 0);
            self::assertSame('cannot_verify', $result['status']);
            self::assertNull($result['recognition']['2027-01']);
            self::assertNull($result['obligations'][0]->settlements[0]->date);
        }
        $knownExpense = $engine->fixed($calendar, 'known', 'contract', '10', '1', 'once', '2027-01-01', '2027-01-01', ['2027-01' => '1'], null, 0);
        self::assertSame('10.00', $knownExpense['recognition']['2027-01']);
        self::assertSame('cannot_verify', $knownExpense['status']);
        $partial = $engine->fixed(new ForecastCalendar('2027-01', 2), 'partial', 'contract', '10', '1', 'once', '2027-01-01', '2027-01-01', ['2027-01' => '1', '2027-02' => null], 0, 0);
        self::assertSame([null, null], array_values($partial['recognition']));
    }

    public function test_direct_costs_use_the_declared_revenue_or_delivery_basis_with_explicit_invoice_dates(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $engine = new ExpenseSchedule;
        $percent = $engine->direct($calendar, 'percent', 'cost-policy', 'revenue_percent', '12.5', ['2027-01' => '1000', '2027-02' => '0'], ['2027-01' => '2027-01-25', '2027-02' => '2027-02-25'], 10, 0);
        self::assertSame(['125.00', '0.00'], array_values($percent['recognition']));
        self::assertSame('2027-02-04', $percent['obligations'][0]->settlements[0]->date);
        $units = $engine->direct($calendar, 'units', 'supplier-rate', 'delivered_units', '3.335', ['2027-01' => '3'], ['2027-01' => '2027-01-31'], 0, 0);
        self::assertSame('10.01', $units['recognition']['2027-01']);
        self::assertNull($units['recognition']['2027-02']);
        self::assertSame('cannot_verify', $units['status']);
        $unknownRate = $engine->direct($calendar, 'unknown', 'source', 'delivered_units', null, [], [], null, 0);
        self::assertNull($unknownRate['recognition']['2027-01']);
    }

    public function test_recognition_outside_declared_scope_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ExpenseSchedule)->fixed(new ForecastCalendar('2027-01', 1), 'cost', 'contract', '10', '1', 'once', '2027-01-01', '2027-01-01', ['2027-02' => '1'], 0, 0);
    }

    public function test_unknown_direct_cost_basis_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ExpenseSchedule)->direct(new ForecastCalendar('2027-01', 1), 'cost', 'contract', 'generic_units', '10', [], [], 0, 0);
    }
}
