<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Carries eligible losses forward month by month.
 *
 * `TaxProjection` applies the same policy to whole planning periods, which is
 * the form a return is filed in. This is the monthly form of it, which is what
 * a release gate reads: a period-level chain cannot say which month a loss was
 * absorbed in, and splitting a period's figures into twelve would invent
 * eleven of them.
 *
 * Two things this never does. A loss does not generate a tax receipt: it
 * raises the carried balance and waits for profit to absorb it. And a month
 * whose taxable profit cannot be established breaks the chain rather than
 * being treated as a zero, because every month after it would otherwise carry
 * a loss balance computed from a figure nobody stated.
 *
 * @phpstan-type ChainMonth array{period:string,profit:string|null,adjustment:string|null,taxable:string|null,loss_used:string|null,loss_closing:string|null,expense:string|null}
 * @phpstan-type ChainResult array{status:string,scope:string,months:list<ChainMonth>,missing:list<string>}
 */
final class MonthlyTaxChain implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /**
     * @param  list<string>  $periods  calendar months, in order
     * @param  array<string,string|null>  $profits  period => accounting profit
     * @param  array<string,string|null>  $adjustments  period => tax adjustments
     * @return ChainResult
     */
    public function calculate(array $periods, array $profits, array $adjustments, ?string $ratePercent,
        ?string $openingEligibleLosses, ?bool $lossesEligible): array
    {
        $rate = DriverNumbers::read($ratePercent);
        $losses = AccountingNumbers::cents($openingEligibleLosses);
        $missing = [];
        if ($rate === null) {
            $missing[] = 'tax.rate_percent';
        }
        if ($losses === null) {
            $missing[] = 'tax.opening_eligible_losses';
        }
        if ($lossesEligible === null) {
            $missing[] = 'tax.losses_eligible';
        }
        $months = [];
        // Once a month cannot be established the balance carried into the next
        // one is unknown too, so the chain stops rather than resuming from a
        // figure that no longer follows from anything.
        $broken = $missing !== [];
        foreach ($periods as $period) {
            $profit = AccountingNumbers::signed($profits[$period] ?? null);
            $adjustment = AccountingNumbers::signed($adjustments[$period] ?? null);
            if ($profit === null) {
                $missing[] = $period.'.profit';
            }
            if ($adjustment === null) {
                $missing[] = $period.'.adjustment';
            }
            if ($broken || $profit === null || $adjustment === null || $rate === null || $losses === null) {
                $broken = true;
                $months[] = ['period' => $period, 'profit' => AccountingNumbers::money($profit),
                    'adjustment' => AccountingNumbers::money($adjustment), 'taxable' => null,
                    'loss_used' => null, 'loss_closing' => null, 'expense' => null];

                continue;
            }
            $taxable = $profit->plus($adjustment);
            // A loss raises the carried balance only where the policy says
            // losses are eligible. Where it does not, the loss is simply not
            // carried, and nothing is refunded for it either.
            $generated = $taxable->isNegative() && $lossesEligible === true ? $taxable->negated() : BigDecimal::zero();
            $positive = BigDecimal::max(0, $taxable);
            $used = BigDecimal::min($losses, $positive);
            $losses = $losses->plus($generated)->minus($used);
            $expense = $positive->minus($used)->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HalfUp);
            $months[] = ['period' => $period, 'profit' => AccountingNumbers::money($profit),
                'adjustment' => AccountingNumbers::money($adjustment), 'taxable' => AccountingNumbers::money($taxable),
                'loss_used' => AccountingNumbers::money($used), 'loss_closing' => AccountingNumbers::money($losses),
                'expense' => AccountingNumbers::money($expense)];
        }

        return ['status' => $missing === [] ? 'pass' : 'cannot_verify',
            'scope' => 'indicative_planning_tax_expense_only', 'months' => $months, 'missing' => $missing];
    }
}
