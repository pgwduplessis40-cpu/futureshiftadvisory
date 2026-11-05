<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * @phpstan-type AssetMonth array{period:string,depreciation:?string,expense:?string,closing_carrying_value:?string,capital_prepayment:?string}
 * @phpstan-type AssetResult array{status:string,tax_basis:string,input:AssetInput,review_required:?bool,months:list<AssetMonth>,cash_movements:list<CashMovement>|null,prior_payment:?CashMovement,missing:list<string>,errors:list<string>}
 */
final class AssetSchedule implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /** @return AssetResult */
    public function calculate(ForecastCalendar $calendar, AssetInput $asset, int $sequence): array
    {
        $missing = $errors = [];
        foreach (['cost' => $asset->cost, 'classification' => $asset->classification, 'policy_source' => $asset->policySource,
            'paid_on' => $asset->paidOn, 'recognition_start_on' => $asset->recognitionStartOn] as $field => $value) {
            if ($value === null || trim($value) === '') {
                $missing[] = $field;
            }
        }
        $cost = DriverNumbers::read($asset->cost);
        $cost = $cost === null ? null : BigDecimal::of(DriverNumbers::money($cost));
        $review = $cost?->isGreaterThanOrEqualTo(1000);
        if ($review && $asset->reviewed !== true) {
            $missing[] = 'classification_review';
        }
        $payment = $asset->classification === null ? null : new CashMovement($asset->id.'.purchase', $asset->source,
            $asset->paidOn, AccountingNumbers::money($cost), 'out', $asset->classification === 'capital' ? 'capex' : 'operating_payment', $sequence);
        $prior = $payment !== null && $payment->date !== null && $payment->date < $calendar->startMonth.'-01' ? $payment : null;
        $cash = $payment === null ? null : ($prior === null ? [$payment] : []);
        $start = $asset->recognitionStartOn === null ? null : substr($asset->recognitionStartOn, 0, 7);
        $residual = DriverNumbers::read($asset->residual);
        $residual = $residual === null ? null : BigDecimal::of(DriverNumbers::money($residual));
        $elapsed = 0;
        if ($start !== null && $start < $calendar->startMonth) {
            $diff = ForecastDates::read($start.'-01')->diff(ForecastDates::read($calendar->startMonth.'-01'));
            $elapsed = $diff->y * 12 + $diff->m;
        }
        $openingDepreciation = $elapsed > 0 ? AccountingNumbers::cents($asset->openingDepreciation) : BigDecimal::zero();
        if ($asset->classification === 'capital') {
            foreach (['residual' => $residual, 'useful_life' => $asset->lifeMonths, 'method' => $asset->method, 'opening_depreciation' => $openingDepreciation] as $field => $value) {
                if ($value === null) {
                    $missing[] = $field;
                }
            }
            if ($cost !== null && $residual !== null && $residual->isGreaterThan($cost)) {
                $errors[] = 'residual_exceeds_cost';
            }
            if ($cost !== null && $residual !== null && $openingDepreciation !== null && $openingDepreciation->isGreaterThan($cost->minus($residual))) {
                $errors[] = 'opening_depreciation_exceeds_depreciable_cost';
            }
        }
        $carrying = $cost !== null && $openingDepreciation !== null ? $cost->minus($openingDepreciation) : null;
        $remainingLife = $asset->lifeMonths === null ? null : max(0, $asset->lifeMonths - $elapsed);
        if ($asset->classification === 'capital' && $remainingLife === 0 && $carrying !== null && $residual !== null && $carrying->isGreaterThan($residual)) {
            $errors[] = 'useful_life_exhausted_with_remaining_value';
        }
        $depreciable = $carrying !== null && $residual !== null ? $carrying->minus($residual) : null;
        $monthly = $depreciable !== null && $remainingLife !== null && $remainingLife > 0
            ? $depreciable->dividedBy($remainingLife, 2, RoundingMode::Down) : null;
        $months = [];
        $capitalKnown = $asset->classification === 'capital' && $cost !== null && $residual !== null && $openingDepreciation !== null
            && $remainingLife !== null && $start !== null && $asset->method !== null && $errors === [];
        foreach ($calendar->periods as $period) {
            $depreciation = null;
            $expense = null;
            $value = null;
            $prepayment = null;
            if ($capitalKnown) {
                assert($carrying !== null && $remainingLife !== null);
                $depreciation = BigDecimal::zero();
                if ($period >= $start && $remainingLife > 0) {
                    assert($monthly !== null);
                    $depreciation = $remainingLife === 1 ? $carrying->minus($residual) : $monthly;
                    $carrying = $carrying->minus($depreciation);
                    $remainingLife--;
                }
                $expense = BigDecimal::zero();
                $value = $period < $start ? BigDecimal::zero() : $carrying;
                $prepayment = $period >= $start ? BigDecimal::zero() : ($asset->paidOn === null ? null
                    : ($asset->paidOn <= ForecastDates::read($period.'-01')->format('Y-m-t') ? $cost : BigDecimal::zero()));
            } elseif ($asset->classification === 'expense' && $cost !== null && $start !== null) {
                $depreciation = BigDecimal::zero();
                $expense = $period === $start ? $cost : BigDecimal::zero();
                $value = BigDecimal::zero();
                $prepayment = BigDecimal::zero();
            }
            $months[] = ['period' => $period, 'depreciation' => AccountingNumbers::money($depreciation),
                'expense' => AccountingNumbers::money($expense), 'closing_carrying_value' => AccountingNumbers::money($value),
                'capital_prepayment' => AccountingNumbers::money($prepayment)];
        }

        return ['status' => AccountingNumbers::status($missing, $errors), 'tax_basis' => 'GST exclusive; GST cash supplied separately',
            'input' => $asset, 'review_required' => $review, 'months' => $months, 'cash_movements' => $cash,
            'prior_payment' => $prior, 'missing' => $missing, 'errors' => $errors];
    }
}
