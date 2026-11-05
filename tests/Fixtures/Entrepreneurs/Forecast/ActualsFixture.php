<?php

declare(strict_types=1);

namespace Tests\Fixtures\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\ActualBalances;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\ActualProfit;
use App\Services\Entrepreneurs\Forecast\ActualSource;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\TradeItem;

final class ActualsFixture
{
    public static function balances(?string $cash = '100', ?string $ar = '0', ?string $ap = '0', ?string $restricted = '0'): ActualBalances
    {
        return new ActualBalances($cash, $restricted, $ar, $ap, '0', '0');
    }

    public static function source(string $period = '2027-01', array $changes = []): ActualSource
    {
        $mappings = [];
        foreach (['profit.revenue', 'profit.operating_expenses', 'profit.depreciation', 'profit.interest', 'profit.other_income', 'profit.income_tax_expense',
            'balance.bank_cash', 'balance.restricted_cash', 'balance.receivables', 'balance.payables', 'balance.customer_advances', 'balance.supplier_prepayments',
            'cash.operating_receipt', 'cash.operating_payment', 'cash.funding', 'cash.capex', 'cash.tax', 'cash.gst'] as $key) {
            $mappings[$key] = ['document:'.$key];
        }

        return new ActualSource(...array_replace(['entityId' => 'synthetic-business', 'provider' => 'manual', 'badge' => 'documented', 'degraded' => false,
            'periodStart' => $period.'-01', 'periodEnd' => (new \DateTimeImmutable($period.'-01'))->format('Y-m-t'), 'importVersion' => 'v1', 'currency' => 'NZD',
            'accountMappings' => $mappings, 'reviewedBy' => 'synthetic-reviewer', 'reviewedOn' => '2027-03-01', 'reviewReference' => 'synthetic-reconciliation', 'reconciled' => true], $changes));
    }

    public static function month(string $period = '2027-01', array $changes = []): ActualMonth
    {
        return new ActualMonth(...array_replace(['period' => $period, 'state' => 'trading', 'source' => self::source($period),
            'profit' => new ActualProfit('0', '0', '0', '0', '0', '0', '0'), 'opening' => self::balances(), 'closing' => self::balances(),
            'movements' => [], 'movementAccounts' => [], 'closingObligations' => []], $changes));
    }

    public static function movement(string $id, string $date, ?string $amount, string $direction, string $category): CashMovement
    {
        return new CashMovement($id, 'synthetic-bank-row', $date, $amount, $direction, $category, 0, true);
    }

    public static function twoMonths(): array
    {
        $january = self::month(changes: ['profit' => new ActualProfit('1000', '500', '0', '0', '0', '500', '100'), 'closing' => self::balances('600'),
            'movements' => [self::movement('jan-sale', '2027-01-10', '1000', 'in', 'operating_receipt'), self::movement('jan-cost', '2027-01-20', '500', 'out', 'operating_payment')],
            'movementAccounts' => ['jan-sale' => 'document:cash.operating_receipt', 'jan-cost' => 'document:cash.operating_payment']]);
        $partial = self::movement('feb-partial', '2027-02-05', '30', 'in', 'operating_receipt');
        $february = self::month('2027-02', ['profit' => new ActualProfit('200', '100', '0', '0', '0', '100', '20'), 'opening' => self::balances('600'), 'closing' => self::balances('700', '70', '40'),
            'movements' => [$partial, self::movement('feb-sale', '2027-02-10', '170', 'in', 'operating_receipt'), self::movement('feb-cost', '2027-02-20', '100', 'out', 'operating_payment')],
            'movementAccounts' => ['feb-partial' => 'document:cash.operating_receipt', 'feb-sale' => 'document:cash.operating_receipt', 'feb-cost' => 'document:cash.operating_payment'],
            'closingObligations' => [new TradeItem('invoice', 'synthetic-invoice', 'receivable', '2027-02-01', '100', [$partial, self::movement('outstanding', '2027-03-05', '70', 'in', 'operating_receipt')]),
                new TradeItem('bill', 'synthetic-bill', 'payable', '2027-02-01', '40', [self::movement('later-payment','2027-06-01','40','out','operating_payment')])]]);

        return [$january, $february];
    }
}
