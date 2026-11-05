<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * @phpstan-type Position array{date:string,receivables:?string,payables:?string,customer_advances:?string,supplier_prepayments:?string}
 * @phpstan-type TradeResult array{status:string,opening:Position,closing:list<Position>,items:list<TradeItem>,missing:list<string>,errors:list<string>}
 */
final class TradeBalances implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash'];
    }

    /** @param list<TradeItem> $items
     * @return TradeResult
     */
    public function calculate(ForecastCalendar $calendar, array $items): array
    {
        $ids = $settlementIds = $missing = $errors = [];
        foreach ($items as $item) {
            if (isset($ids[$item->id])) {
                throw new \InvalidArgumentException('Trade identities must be unique.');
            }
            $ids[$item->id] = true;
            foreach (['raised_on' => $item->raisedOn, 'amount' => $item->amount, 'settlements' => $item->settlements] as $field => $value) {
                if ($value === null) {
                    $missing[] = $item->id.'.'.$field;
                }
            }
            $settled = BigDecimal::zero();
            foreach ($item->settlements ?? [] as $settlement) {
                if (isset($settlementIds[$settlement->id])) {
                    throw new \InvalidArgumentException('A settlement cannot be allocated to more than one trade item.');
                }
                $settlementIds[$settlement->id] = true;
                foreach (['date' => $settlement->date, 'amount' => $settlement->amount] as $field => $value) {
                    if ($value === null) {
                        $missing[] = $settlement->id.'.'.$field;
                    }
                }
                if ($settlement->amount !== null) {
                    $settled = $settled->plus(DriverNumbers::money(BigDecimal::of($settlement->amount)));
                }
            }
            if ($item->amount !== null && $settled->isGreaterThan(DriverNumbers::money(BigDecimal::of($item->amount)))) {
                $errors[] = $item->id.'.settlements_exceed_amount';
            }
        }
        $complete = $missing === [] && $errors === [];
        $openingDate = ForecastDates::shift($calendar->startMonth.'-01', -1);
        $closing = [];
        foreach ($calendar->periods as $period) {
            $closing[] = $this->position(ForecastDates::read($period.'-01')->format('Y-m-t'), $items, $complete);
        }

        return ['status' => $errors !== [] ? 'fail' : ($missing !== [] ? 'cannot_verify' : 'pass'),
            'opening' => $this->position($openingDate, $items, $complete), 'closing' => $closing,
            'items' => $items, 'missing' => $missing, 'errors' => $errors];
    }

    /** @param list<TradeItem> $items
     * @return Position
     */
    private function position(string $date, array $items, bool $complete): array
    {
        $totals = ['receivables' => BigDecimal::zero(), 'payables' => BigDecimal::zero(),
            'customer_advances' => BigDecimal::zero(), 'supplier_prepayments' => BigDecimal::zero()];
        if ($complete) {
            foreach ($items as $item) {
                assert($item->amount !== null && $item->raisedOn !== null);
                $raised = $item->raisedOn <= $date ? BigDecimal::of($item->amount) : BigDecimal::zero();
                $paid = BigDecimal::zero();
                foreach ($item->settlements ?? [] as $settlement) {
                    assert($settlement->amount !== null && $settlement->date !== null);
                    if ($settlement->date <= $date) {
                        $paid = $paid->plus(DriverNumbers::money(BigDecimal::of($settlement->amount)));
                    }
                }
                $balance = BigDecimal::of(DriverNumbers::money($raised))->minus($paid);
                $key = $balance->isNegative()
                    ? ($item->kind === 'receivable' ? 'customer_advances' : 'supplier_prepayments')
                    : ($item->kind === 'receivable' ? 'receivables' : 'payables');
                $totals[$key] = $totals[$key]->plus($balance->abs());
            }
        }

        return ['date' => $date, 'receivables' => $complete ? DriverNumbers::money($totals['receivables']) : null,
            'payables' => $complete ? DriverNumbers::money($totals['payables']) : null,
            'customer_advances' => $complete ? DriverNumbers::money($totals['customer_advances']) : null,
            'supplier_prepayments' => $complete ? DriverNumbers::money($totals['supplier_prepayments']) : null];
    }
}
