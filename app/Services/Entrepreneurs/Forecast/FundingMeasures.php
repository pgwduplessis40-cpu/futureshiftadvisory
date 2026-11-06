<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * Dated funding measures for one scenario.
 *
 * `C[s,t]` is the dated unrestricted closing cash *before* any proposed new
 * facility. It is read from the cash ledger's checkpoints rather than
 * recomputed here, so verified opening cash and each committed funding source
 * are counted exactly once, on their own dates, by the engine that already
 * owns that rule.
 *
 * The two measures are deliberately separable and never summed:
 *
 *   cash_deficit            = max(0, -min_t(C))
 *   reserve_target_shortfall = max(0, max_t(reserve_target - C))
 *
 * The shortfall already contains any cash deficit, because a balance that went
 * negative is further below the reserve target than any positive balance. A
 * reader who adds them double-counts, and subtracting opening cash or funding
 * again double-counts the other way: both are already inside C.
 *
 * @phpstan-type Checkpoint array{date:string,movement_id:?string,balance:?string}
 * @phpstan-type FundingResult array{engine_version:string,scope:string,status:string,lowest_cash:?string,lowest_cash_date:?string,cash_deficit:?string,reserve_policy:ReservePolicy,reserve_target:?string,reserve_shortfall:?string,reserve_shortfall_date:?string,checkpoints_considered:int,missing:list<string>}
 */
final class FundingMeasures implements ProvidesMethodology
{
    public const VERSION = 'phase-2d.1';

    public static function methodologyIds(): array
    {
        return ['entrepreneur.scenarios_metrics_funding'];
    }

    /**
     * @param  array{status:string,checkpoints:list<Checkpoint>,lowest_cash:?string,lowest_cash_date:?string,missing:list<string>}  $ledger
     * @return FundingResult
     */
    public function calculate(array $ledger, ReservePolicy $policy): array
    {
        $missing = $policy->missing();
        foreach ($ledger['missing'] as $item) {
            $missing[] = 'cash.'.$item;
        }

        $dated = array_values(array_filter(
            $ledger['checkpoints'],
            static fn (array $checkpoint): bool => $checkpoint['balance'] !== null
        ));
        if (count($dated) !== count($ledger['checkpoints'])) {
            $missing[] = 'cash.checkpoint_balances';
        }

        $target = $policy->target();
        $cashDeficit = $this->cashDeficit($ledger['lowest_cash']);
        [$shortfall, $shortfallDate] = $this->reserveShortfall($dated, $target);

        // A measure computed from an incomplete ledger would read as a funding
        // answer. Withhold the figures and report what is missing instead.
        $usable = $missing === [];
        sort($missing);

        return [
            'engine_version' => self::VERSION,
            'scope' => 'before_proposed_facility',
            'status' => $usable ? 'pass' : 'cannot_verify',
            'lowest_cash' => $usable ? $ledger['lowest_cash'] : null,
            'lowest_cash_date' => $usable ? $ledger['lowest_cash_date'] : null,
            'cash_deficit' => $usable && $cashDeficit !== null ? DriverNumbers::money($cashDeficit) : null,
            'reserve_policy' => $policy,
            'reserve_target' => $usable && $target !== null ? DriverNumbers::money($target) : null,
            'reserve_shortfall' => $usable && $shortfall !== null ? DriverNumbers::money($shortfall) : null,
            'reserve_shortfall_date' => $usable ? $shortfallDate : null,
            'checkpoints_considered' => count($dated),
            'missing' => $missing,
        ];
    }

    /** max(0, -min_t(C)): the cash needed to avoid a negative balance.
     *
     * A positive lowest balance needs no funding, so the floor is zero; the
     * measure is never a negative requirement.
     */
    private function cashDeficit(?string $lowestCash): ?BigDecimal
    {
        $lowest = DriverNumbers::readSigned($lowestCash);
        if ($lowest === null) {
            return null;
        }

        return $lowest->isNegative() ? $lowest->negated() : BigDecimal::zero();
    }

    /** max(0, max_t(reserve_target - C[s,t])) with the date that produced it.
     *
     * @param  list<Checkpoint>  $checkpoints
     * @return array{0:?BigDecimal,1:?string}
     */
    private function reserveShortfall(array $checkpoints, ?BigDecimal $target): array
    {
        if ($target === null || $checkpoints === []) {
            return [null, null];
        }

        $worst = null;
        $worstDate = null;
        foreach ($checkpoints as $checkpoint) {
            $balance = DriverNumbers::readSigned($checkpoint['balance']);
            if ($balance === null) {
                continue;
            }
            $gap = $target->minus($balance);
            if ($worst === null || $gap->isGreaterThan($worst)) {
                $worst = $gap;
                $worstDate = $checkpoint['date'];
            }
        }
        if ($worst === null) {
            return [null, null];
        }

        return $worst->isNegative() ? [BigDecimal::zero(), null] : [$worst, $worstDate];
    }
}
