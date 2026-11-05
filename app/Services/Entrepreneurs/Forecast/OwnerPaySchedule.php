<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * @phpstan-type PayRate array{date:string,weekly_amount:?string,average_monthly_amount:?string,trailing_average_revenue:?string}
 * @phpstan-type PayResult array{status:string,payments:list<CashMovement>,rates:list<PayRate>,missing:list<string>}
 */
final class OwnerPaySchedule implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash'];
    }

    /**
     * The latest effective-date group replaces the previous policy. Each group
     * requires one base and may have conditional tiers. Eligibility is recomputed
     * for every weekly payment; a previously reached tier is never latched on.
     *
     * @param  list<OwnerPayStep>  $steps
     * @param  array<string,?string>  $revenue
     * @return PayResult
     */
    public function calculate(ForecastCalendar $calendar, string $source, string $firstPaymentOn, array $steps, array $revenue, int $sequence): array
    {
        $policies = [];
        $seen = [];
        foreach ($steps as $step) {
            $identity = $step->effectiveOn.':'.$step->trigger.':'.($step->threshold === null ? '?' : BigDecimal::of($step->threshold)->strippedOfTrailingZeros());
            if (isset($seen[$identity])) {
                throw new \InvalidArgumentException('Owner-pay policy dates cannot contain duplicate bases or thresholds.');
            }
            $seen[$identity] = true;
            $policies[$step->effectiveOn][] = $step;
        }
        ksort($policies);
        foreach ($revenue as $period => $amount) {
            ForecastDates::read($period.'-01');
            DriverNumbers::read($amount);
        }
        $payments = $rates = $missing = [];
        foreach (ForecastDates::recur($firstPaymentOn, ForecastDates::end($calendar), 'weekly') as $date) {
            if ($date < $calendar->startMonth.'-01') {
                continue;
            }
            $policy = [];
            foreach ($policies as $effective => $candidate) {
                if ($effective <= $date) {
                    $policy = $candidate;
                }
            }
            [$weekly, $average, $unknown] = $this->rate($date, $policy, $revenue);
            foreach ($unknown as $field) {
                $missing[] = $date.'.'.$field;
            }
            $payments[] = new CashMovement('owner_pay.'.$date, $source, $date, $weekly, 'out', 'owner_pay', $sequence);
            $rates[] = ['date' => $date, 'weekly_amount' => $weekly,
                'average_monthly_amount' => $weekly === null ? null : (string) BigDecimal::of($weekly)->multipliedBy(52)->dividedBy(12, 2, RoundingMode::HalfUp),
                'trailing_average_revenue' => $average];
        }

        return ['status' => $missing === [] ? 'pass' : 'cannot_verify', 'payments' => $payments, 'rates' => $rates, 'missing' => $missing];
    }

    /** @param list<OwnerPayStep> $policy
     * @param  array<string,?string>  $revenue
     * @return array{?string,?string,list<string>}
     */
    private function rate(string $date, array $policy, array $revenue): array
    {
        $missing = [];
        $base = null;
        $conditional = [];
        foreach ($policy as $step) {
            if ($step->trigger === 'base') {
                $base = $step;
            } else {
                $conditional[] = $step;
            }
        }
        if ($base === null) {
            $missing[] = 'base_policy';
        }
        $total = BigDecimal::zero();
        if ($conditional !== []) {
            $month = ForecastDates::read(substr($date, 0, 7).'-01');
            for ($index = 1; $index <= 3; $index++) {
                $period = $month->modify('-'.$index.' months')->format('Y-m');
                $amount = DriverNumbers::read($revenue[$period] ?? null);
                if ($amount === null) {
                    $missing[] = 'revenue.'.$period;
                } else {
                    $total = $total->plus($amount);
                }
            }
        }
        $selected = $base;
        $selectedThreshold = null;
        foreach ($conditional as $step) {
            $threshold = DriverNumbers::read($step->threshold);
            if ($threshold === null) {
                $missing[] = 'trigger_threshold';
            } elseif ($total->isGreaterThanOrEqualTo($threshold->multipliedBy(3)) && ($selectedThreshold === null || $threshold->isGreaterThan($selectedThreshold))) {
                $selected = $step;
                $selectedThreshold = $threshold;
            }
        }
        if ($selected?->weeklyAmount === null) {
            $missing[] = 'weekly_amount';
        }
        $weekly = $missing === [] && $selected?->weeklyAmount !== null ? DriverNumbers::money(BigDecimal::of($selected->weeklyAmount)) : null;
        $average = $conditional !== [] && ! array_filter($missing, fn (string $field): bool => str_starts_with($field, 'revenue.'))
            ? (string) $total->dividedBy(3, 2, RoundingMode::HalfUp) : null;

        return [$weekly, $average, $missing];
    }
}
