<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast\Storage;

use App\Services\Entrepreneurs\Forecast\ActualBalances;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\ActualProfit;
use App\Services\Entrepreneurs\Forecast\ActualSource;

/**
 * Rebuilds reviewed monthly actuals from stored JSON.
 *
 * Actuals carry their own review provenance, so the absent-stays-absent rule
 * matters most here: a month that loses its reviewer, period or reconciliation
 * flag on reload would read as reviewed when it never was.
 */
final class ActualsCodec
{
    /** @return list<ActualMonth> */
    public static function months(mixed $node, string $key): array
    {
        $months = [];
        foreach (StoredValues::items($node, $key) as $item) {
            $months[] = self::month($item);
        }

        return $months;
    }

    public static function month(mixed $node): ActualMonth
    {
        return new ActualMonth(
            StoredValues::requiredText($node, 'period'),
            StoredValues::text($node, 'state'),
            self::source(StoredValues::child($node, 'source')),
            self::profit(StoredValues::child($node, 'profit')),
            self::balances(StoredValues::child($node, 'opening')),
            self::balances(StoredValues::child($node, 'closing')),
            StoredValues::has($node, 'movements') ? DriverCodec::movements($node, 'movements') : null,
            StoredValues::has($node, 'movement_accounts') ? self::accounts($node, 'movement_accounts') : null,
            StoredValues::has($node, 'closing_obligations') ? DriverCodec::tradeItems($node, 'closing_obligations') : null,
            StoredValues::text($node, 'pre_trading_evidence'),
        );
    }

    public static function source(mixed $node): ActualSource
    {
        return new ActualSource(
            StoredValues::text($node, 'entity_id'),
            StoredValues::text($node, 'provider'),
            StoredValues::text($node, 'badge'),
            StoredValues::flag($node, 'degraded'),
            StoredValues::text($node, 'period_start'),
            StoredValues::text($node, 'period_end'),
            StoredValues::text($node, 'import_version'),
            StoredValues::text($node, 'currency'),
            StoredValues::has($node, 'account_mappings') ? self::mappings($node, 'account_mappings') : null,
            StoredValues::text($node, 'reviewed_by'),
            StoredValues::text($node, 'reviewed_on'),
            StoredValues::text($node, 'review_reference'),
            StoredValues::flag($node, 'reconciled'),
        );
    }

    public static function profit(mixed $node): ActualProfit
    {
        return new ActualProfit(
            StoredValues::text($node, 'revenue'),
            StoredValues::text($node, 'operating_expenses'),
            StoredValues::text($node, 'depreciation'),
            StoredValues::text($node, 'interest'),
            StoredValues::text($node, 'other_income'),
            StoredValues::text($node, 'profit_before_tax'),
            StoredValues::text($node, 'income_tax_expense'),
        );
    }

    public static function balances(mixed $node): ActualBalances
    {
        return new ActualBalances(
            StoredValues::text($node, 'bank_cash'),
            StoredValues::text($node, 'restricted_cash'),
            StoredValues::text($node, 'receivables'),
            StoredValues::text($node, 'payables'),
            StoredValues::text($node, 'customer_advances'),
            StoredValues::text($node, 'supplier_prepayments'),
        );
    }

    /** @return array<string,list<string>> */
    private static function mappings(mixed $node, string $key): array
    {
        $mappings = [];
        $child = StoredValues::child($node, $key);
        foreach (StoredValues::keys($node, $key) as $account) {
            $mappings[$account] = StoredValues::textItems($child, $account);
        }

        return $mappings;
    }

    /** @return array<string,string> */
    private static function accounts(mixed $node, string $key): array
    {
        $accounts = [];
        $child = StoredValues::child($node, $key);
        foreach (StoredValues::keys($node, $key) as $movement) {
            $accounts[$movement] = StoredValues::requiredText($child, $movement);
        }

        return $accounts;
    }
}
