<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * Monthly profit before tax, built from the sections the reconstruction holds.
 *
 * The model records revenue in two places and costs in two places, and for a
 * long time nothing said how they related. They are separate: revenue streams
 * and fixed costs are the recurring business, and trade items are opening
 * debtors and creditors plus one-offs. So the two are added rather than
 * reconciled, and nothing here double counts.
 *
 * A trade item's `raised_on` date decides whether it belongs to this horizon
 * at all. One raised before the forecast opens is an opening debtor or
 * creditor: its cash arrives inside the horizon but the revenue or cost was
 * earned before it, so it changes no month's profit here. One raised during
 * the forecast is a one-off belonging to the month it was raised in.
 *
 * Everything is recognised, not paid. The cash side of each of these lives in
 * the ledger and answers a different question.
 *
 * Interest is reported on its own line rather than inside the one-off costs it
 * arrives with. It is a financing charge, not a cost of trading, and a
 * decomposition that reads revenue less costs, depreciation and interest
 * cannot be stated from a figure that has interest buried in it.
 *
 * @phpstan-type ProfitMonth array{period:string,revenue:string|null,direct_costs:string|null,fixed_costs:string|null,one_off_income:string|null,one_off_costs:string|null,interest:string|null,depreciation:string|null,asset_expense:string|null,owner_pay:string|null,profit:string|null}
 * @phpstan-type ProfitResult array{status:string,scope:string,months:list<ProfitMonth>,missing:list<string>}
 */
final class MonthlyProfit implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /**
     * Payable categories that are an expense of the month they are raised in.
     *
     * The rest are not costs of trading. Capital purchases are carried as
     * assets and reach profit as depreciation, so counting the invoice too
     * would charge the same spend twice. Tax has its own chain, GST is a
     * balance-sheet flow, and loan principal and distributions never touch
     * profit at all. Owner pay is handled separately, because whether it is
     * deductible depends on the entity rather than on the payment.
     *
     * @var list<string>
     */
    public const EXPENSE_CATEGORIES = ['operating_payment', 'interest', 'fees'];

    /**
     * @param  array<string,array{base_revenue:string|null,base_contractor_cost:string|null}>  $revenue
     * @param  array<string,string|null>  $fixedCosts
     * @param  list<TradeItem>  $tradeItems
     * @param  array<string,array{depreciation:string|null,expense:string|null}>  $assets
     * @param  array<string,string>  $ownerPay  period => cash paid in that month
     * @return ProfitResult
     */
    public function calculate(ForecastCalendar $calendar, array $revenue, array $fixedCosts, array $tradeItems,
        array $assets, array $ownerPay, ?bool $ownerPayDeductible): array
    {
        $opens = $calendar->startMonth.'-01';
        $missing = [];
        if ($ownerPayDeductible === null && $ownerPay !== []) {
            // Whether the owner's pay is an expense or a drawing is an entity
            // question, and the two give different profits off the same cash.
            // Nothing here guesses it from the entity basis, which is free text.
            $missing[] = 'owner_pay.deductible';
        }
        [$income, $costs, $interest, $unclassified] = $this->oneOffs($calendar, $tradeItems, $opens);
        $missing = [...$missing, ...$unclassified];
        $months = [];
        foreach ($calendar->periods as $period) {
            $parts = [
                'revenue' => $revenue[$period]['base_revenue'] ?? null,
                'direct_costs' => $revenue[$period]['base_contractor_cost'] ?? null,
                'fixed_costs' => $fixedCosts[$period] ?? null,
                'one_off_income' => $income[$period] ?? '0.00',
                'one_off_costs' => $costs[$period] ?? '0.00',
                'interest' => $interest[$period] ?? '0.00',
                'depreciation' => $assets[$period]['depreciation'] ?? '0.00',
                'asset_expense' => $assets[$period]['expense'] ?? '0.00',
                // A drawing is not an expense, so an entity that treats it as
                // one charges nothing. Where nobody has said which it is, only
                // the months that actually carry pay are withheld: a month
                // with none charges nothing under either answer, and
                // withholding it would report a gap that does not exist.
                'owner_pay' => match ($ownerPayDeductible) {
                    false => '0.00',
                    true => $ownerPay[$period] ?? '0.00',
                    default => isset($ownerPay[$period]) ? null : '0.00',
                },
            ];
            foreach ($parts as $name => $value) {
                if ($value === null && $name !== 'owner_pay') {
                    $missing[] = $period.'.'.$name;
                }
            }
            $months[] = ['period' => $period, ...$parts,
                'profit' => $this->incomplete($parts) ? null : $this->total($parts)];
        }

        return ['status' => $missing === [] ? 'pass' : 'cannot_verify',
            'scope' => 'recognised_profit_before_tax', 'months' => $months, 'missing' => $missing];
    }

    /**
     * One-off income and costs by the month they were raised in.
     *
     * An item raised outside the calendar belongs to no month here. Before the
     * forecast opens it is an opening debtor or creditor, which the opening
     * balances already carry; after it closes it is outside the horizon.
     *
     * Interest comes back apart from the other costs. It arrives the same way
     * and belongs to the same month, but it is a financing charge rather than
     * a cost of trading, and the profit decomposition states the two
     * separately.
     *
     * @param  list<TradeItem>  $items
     * @return array{0:array<string,string>,1:array<string,string>,2:array<string,string>,3:list<string>}
     */
    private function oneOffs(ForecastCalendar $calendar, array $items, string $opens): array
    {
        $closes = ForecastDates::end($calendar);
        $income = $costs = $interest = [];
        $unclassified = [];
        $within = array_flip($calendar->periods);
        foreach ($items as $item) {
            if ($item->raisedOn === null) {
                // A trade item nobody has dated belongs to no month, and
                // choosing one would place revenue or cost in a period
                // nobody said it was earned or incurred in.
                $unclassified[] = $item->id.'.raised_on';

                continue;
            }
            if ($item->raisedOn < $opens || $item->raisedOn > $closes) {
                continue;
            }
            $period = substr($item->raisedOn, 0, 7);
            if (! isset($within[$period])) {
                continue;
            }
            if ($item->amount === null) {
                $unclassified[] = $item->id.'.amount';

                continue;
            }
            $category = $this->category($item);
            if ($category === null) {
                $unclassified[] = $item->id.'.category';

                continue;
            }
            if ($item->kind === 'receivable') {
                // Only a trading receipt is revenue. A drawn-down facility, a
                // tax refund or the proceeds of selling an asset all arrive as
                // receivables and none of them is revenue of the month.
                if ($category === 'operating_receipt') {
                    $income[$period] = (string) BigDecimal::of($income[$period] ?? '0')->plus($item->amount);
                }

                continue;
            }
            if ($category === 'interest') {
                $interest[$period] = (string) BigDecimal::of($interest[$period] ?? '0')->plus($item->amount);

                continue;
            }
            if (in_array($category, self::EXPENSE_CATEGORIES, true)) {
                $costs[$period] = (string) BigDecimal::of($costs[$period] ?? '0')->plus($item->amount);
            }
        }

        // At cents, because they are read beside the recurring figures and
        // summed into a profit with them. A total written two ways is the
        // same figure looking like two.
        $money = fn (array $totals): array => array_map(
            fn (string $total): string => (string) AccountingNumbers::money(BigDecimal::of($total)), $totals);

        return [$money($income), $money($costs), $money($interest), $unclassified];
    }

    /**
     * The one category an item's settlements agree on, or none.
     *
     * An item settled under two different categories has no single nature, and
     * picking one of them would be a claim nobody made. An item whose terms
     * nobody has recorded has none either: it is known to be money owed, and
     * not known to be an operating cost rather than a capital purchase, which
     * are charged to profit in entirely different ways.
     */
    private function category(TradeItem $item): ?string
    {
        $categories = array_values(array_unique(array_map(
            fn (CashMovement $movement): string => $movement->category, $item->settlements ?? [])));

        return count($categories) === 1 ? $categories[0] : null;
    }

    /** @param array<string,string|null> $parts */
    private function incomplete(array $parts): bool
    {
        return in_array(null, $parts, true);
    }

    /** @param array<string,string|null> $parts */
    private function total(array $parts): string
    {
        $profit = BigDecimal::of($parts['revenue'] ?? '0')->plus($parts['one_off_income'] ?? '0');
        foreach (['direct_costs', 'fixed_costs', 'one_off_costs', 'interest', 'depreciation', 'asset_expense', 'owner_pay'] as $cost) {
            $profit = $profit->minus($parts[$cost] ?? '0');
        }

        return AccountingNumbers::money($profit) ?? '0.00';
    }
}
