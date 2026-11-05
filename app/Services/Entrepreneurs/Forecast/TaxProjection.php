<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * @phpstan-type TaxResultRow array{start_on:string,end_on:string,taxable_before_losses:?string,loss_opening:?string,loss_generated:?string,loss_used:?string,loss_closing:?string,taxable_after_losses:?string,tax_expense:?string}
 * @phpstan-type TaxResult array{status:string,scope:string,policy:TaxPolicy,periods:list<TaxResultRow>,missing:list<string>,errors:list<string>}
 */
final class TaxProjection implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /** @param list<TaxPeriod> $periods
     * @return TaxResult
     */
    public function calculate(TaxPolicy $policy, ?string $openingEligibleLosses, array $periods): array
    {
        DriverNumbers::read($openingEligibleLosses);
        $losses = AccountingNumbers::cents($openingEligibleLosses);
        $missing = $errors = $rows = [];
        foreach ($policy->jsonSerialize() as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = 'policy.'.$key;
            }
        }
        if ($losses === null) {
            $missing[] = 'opening_eligible_losses';
        }
        if ($policy->lossesEligible === false && $losses !== null && ! $losses->isZero()) {
            $errors[] = 'ineligible_policy_has_eligible_opening_losses';
        }
        if ($periods === []) {
            $missing[] = 'tax_periods';
        }
        usort($periods, fn (TaxPeriod $a, TaxPeriod $b): int => strcmp($a->startOn, $b->startOn));
        $previousEnd = null;
        $policyKnown = $missing === [] && $errors === [];
        foreach ($periods as $period) {
            if ($previousEnd !== null && $period->startOn !== ForecastDates::shift($previousEnd, 1)) {
                throw new \InvalidArgumentException('Planning tax periods must be contiguous and must not overlap.');
            }
            $previousEnd = $period->endOn;
            $profit = AccountingNumbers::cents($period->accountingProfit);
            $adjustments = AccountingNumbers::cents($period->taxAdjustments);
            if ($profit === null || $adjustments === null) {
                $missing[] = $period->startOn.'.profit_or_adjustments';
            }
            $before = $profit !== null && $adjustments !== null ? $profit->plus($adjustments) : null;
            $opening = $losses;
            $generated = $used = $after = $tax = null;
            if ($policyKnown && $before !== null && $losses !== null) {
                assert($policy->ratePercent !== null);
                $generated = $before->isNegative() && $policy->lossesEligible === true ? $before->negated() : BigDecimal::zero();
                $positive = BigDecimal::max(0, $before);
                $used = BigDecimal::min($losses, $positive);
                $after = $positive->minus($used);
                $losses = $losses->plus($generated)->minus($used);
                $tax = $after->multipliedBy($policy->ratePercent)->dividedByExact(100);
            } else {
                $losses = null;
            }
            $rows[] = ['start_on' => $period->startOn, 'end_on' => $period->endOn, 'taxable_before_losses' => AccountingNumbers::money($before),
                'loss_opening' => AccountingNumbers::money($opening), 'loss_generated' => AccountingNumbers::money($generated),
                'loss_used' => AccountingNumbers::money($used), 'loss_closing' => AccountingNumbers::money($losses),
                'taxable_after_losses' => AccountingNumbers::money($after), 'tax_expense' => AccountingNumbers::money($tax)];
        }

        return ['status' => AccountingNumbers::status($missing, $errors), 'scope' => 'indicative_planning_tax_expense_only',
            'policy' => $policy, 'periods' => $rows, 'missing' => $missing, 'errors' => $errors];
    }
}
