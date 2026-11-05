<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

final class ActualMonthReview implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /** @return array{missing:list<string>,errors:list<string>} */
    public function inspect(ActualMonth $month, string $entityId): array
    {
        $missing = $errors = [];
        $source = $month->source;
        foreach ($source->jsonSerialize() as $field => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = 'source.'.$field;
            }
        }
        if ($source->entityId !== null && $source->entityId !== $entityId) {
            $errors[] = 'source.entity_scope_mismatch';
        }
        if ($source->currency !== null && $source->currency !== 'NZD') {
            $errors[] = 'source.currency_mismatch';
        }
        if (($source->periodStart !== null && $source->periodStart !== $month->period.'-01')
            || ($source->periodEnd !== null && $source->periodEnd !== ForecastDates::read($month->period.'-01')->format('Y-m-t'))) {
            $errors[] = 'source.period_mismatch';
        }
        if ($source->degraded !== false || ($source->provider === 'xero' && $source->badge !== 'live')
            || ($source->provider === 'manual' && $source->badge !== 'documented')) {
            $missing[] = 'source.usable_provenance';
        }
        if ($source->reconciled !== true) {
            $missing[] = 'source.reviewed_reconciliation';
        }
        if ($source->reviewedOn !== null && $source->reviewedOn < ForecastDates::read($month->period.'-01')->format('Y-m-t')) {
            $errors[] = 'source.review_precedes_completed_period';
        }
        foreach (['state' => $month->state, 'movements' => $month->movements, 'movement_accounts' => $month->movementAccounts,
            'closing_obligations' => $month->closingObligations] as $field => $value) {
            if ($value === null) {
                $missing[] = $field;
            }
        }
        foreach (['profit' => $month->profit->jsonSerialize(), 'opening' => $month->opening->jsonSerialize(), 'closing' => $month->closing->jsonSerialize()] as $group => $values) {
            foreach ($values as $field => $value) {
                if ($value === null) {
                    $missing[] = $group.'.'.$field;
                }
                if ($field === 'profit_before_tax') {
                    continue;
                }
                $key = ($group === 'profit' ? 'profit' : 'balance').'.'.$field;
                $codes = $source->accountMappings[$key] ?? null;
                if ($codes === null || ($codes === [] && $value !== null && ! BigDecimal::of($value)->isZero())) {
                    $missing[] = 'mapping.'.$key;
                }
            }
        }
        if ($month->state === 'pre_trading') {
            if (trim($month->preTradingEvidence ?? '') === '') {
                $missing[] = 'pre_trading_evidence';
            }
            if ($month->profit->revenue !== null && ! BigDecimal::of($month->profit->revenue)->isZero()) {
                $errors[] = 'pre_trading_has_revenue';
            }
        }
        $profit = $month->profit->jsonSerialize();
        if (! in_array(null, array_intersect_key($profit, array_flip(['revenue', 'other_income', 'operating_expenses', 'depreciation', 'interest', 'profit_before_tax'])), true)) {
            assert($profit['revenue'] !== null && $profit['other_income'] !== null);
            $expected = BigDecimal::of(DriverNumbers::money(BigDecimal::of($profit['revenue'])))->plus(DriverNumbers::money(BigDecimal::of($profit['other_income'])));
            foreach (['operating_expenses', 'depreciation', 'interest'] as $field) {
                assert($profit[$field] !== null);
                $expected = $expected->minus(DriverNumbers::money(BigDecimal::of($profit[$field])));
            }
            if (AccountingNumbers::same((string) $expected, $profit['profit_before_tax']) === false) {
                $errors[] = 'profit.bridge_mismatch';
            }
        }
        $validMovements = [];
        $ids = [];
        foreach ($month->movements ?? [] as $movement) {
            $ids[] = $movement->id;
            if ($movement->category === 'restricted_transfer' || ($movement->date !== null && substr($movement->date, 0, 7) !== $month->period)) {
                $errors[] = $movement->id.'.not_an_in_period_bank_movement';

                continue;
            }
            $account = $month->movementAccounts[$movement->id] ?? null;
            $codes = $source->accountMappings['cash.'.$movement->category] ?? null;
            if ($account === null || $codes === null) {
                $missing[] = $movement->id.'.account_mapping';
            } elseif (! in_array($account, $codes, true)) {
                $errors[] = $movement->id.'.account_mapping_mismatch';
            }
            $validMovements[] = $movement;
        }
        if (array_diff(array_keys($month->movementAccounts ?? []), $ids) !== []) {
            $errors[] = 'movement_mapping_has_unbound_rows';
        }
        $ledger = (new CashLedger)->calculate(new ForecastCalendar($month->period, 1), new CashOpening($month->opening->bankCash, $source->reviewReference, $source->reconciled), $validMovements);
        $missing = [...$missing, ...$ledger['missing']];
        if (AccountingNumbers::same($ledger['months'][0]['closing_cash'], $month->closing->bankCash) === false) {
            $errors[] = 'bank.closing_reconciliation_mismatch';
        }
        foreach ([$month->opening, $month->closing] as $balances) {
            if ($balances->bankCash !== null && $balances->restrictedCash !== null && BigDecimal::of($balances->restrictedCash)->isGreaterThan(BigDecimal::max(0, $balances->bankCash))) {
                $errors[] = 'restricted_cash_exceeds_bank';
            }
        }
        if ($month->closingObligations !== null) {
            $cashById = [];
            foreach ($validMovements as $movement) {
                $cashById[$movement->id] = $movement;
            }
            foreach ($month->closingObligations as $item) {
                $scheduled = BigDecimal::zero();
                foreach ($item->settlements ?? [] as $settlement) {
                    $scheduled = $scheduled->plus(AccountingNumbers::cents($settlement->amount) ?? 0);
                    if ($settlement->date !== null && substr($settlement->date, 0, 7) === $month->period) {
                        $bankEntry = $cashById[$settlement->id] ?? null;
                        if ($bankEntry === null || $bankEntry->date !== $settlement->date || $bankEntry->direction !== $settlement->direction
                            || $bankEntry->category !== $settlement->category || AccountingNumbers::same($bankEntry->amount, $settlement->amount) !== true) {
                            $errors[] = 'obligations.'.$settlement->id.'.bank_settlement_mismatch';
                        }
                    }
                }
                if ($item->amount !== null && $scheduled->isLessThan(AccountingNumbers::cents($item->amount))) {
                    $missing[] = 'obligations.'.$item->id.'.remaining_settlement_schedule';
                }
            }
            $next = ForecastDates::read($month->period.'-01')->modify('+1 month')->format('Y-m');
            $trade = (new TradeBalances)->calculate(new ForecastCalendar($next, 1), $month->closingObligations);
            $missing = [...$missing, ...array_map(fn (string $s): string => 'obligations.'.$s, $trade['missing'])];
            $errors = [...$errors, ...array_map(fn (string $s): string => 'obligations.'.$s, $trade['errors'])];
            foreach (['receivables', 'payables', 'customer_advances', 'supplier_prepayments'] as $field) {
                if (AccountingNumbers::same($trade['opening'][$field], $month->closing->jsonSerialize()[$field]) === false) {
                    $errors[] = 'obligations.'.$field.'_mismatch';
                }
            }
        }

        return ['missing' => array_values(array_unique($missing)), 'errors' => array_values(array_unique($errors))];
    }
}
